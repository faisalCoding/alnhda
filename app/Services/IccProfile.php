<?php

namespace App\Services;

/**
 * Carries a photograph's colour profile across GD, which drops it.
 *
 * Phones shoot in Display P3. GD keeps the pixel values but forgets the
 * profile that gives them meaning, so a browser reads them as sRGB and the
 * greens and blues come out flat. Writing the original profile back into the
 * WebP keeps the colours as they were taken.
 */
class IccProfile
{
    /**
     * The profile sits in the first few segments of a file, ahead of the
     * pixels, so there is no need to read a 20MB photograph to find it.
     */
    private const HEADER_BYTES = 1048576;

    /**
     * The sRGB primaries as an ICC profile records them, adapted to D50.
     *
     * @var array<string, array{float, float, float}>
     */
    private const SRGB_COLORANTS = [
        'rXYZ' => [0.4361, 0.2225, 0.0139],
        'gXYZ' => [0.3851, 0.7169, 0.0971],
        'bXYZ' => [0.1431, 0.0606, 0.7141],
    ];

    /**
     * Reads the embedded profile of a JPEG, PNG or WebP file, if it has one.
     */
    public static function fromFile(string $path): ?string
    {
        $head = @file_get_contents($path, false, null, 0, self::HEADER_BYTES);

        if (! is_string($head) || $head === '') {
            return null;
        }

        $profile = match (true) {
            str_starts_with($head, "\xFF\xD8") => self::fromJpeg($head),
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => self::fromPng($head),
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => self::fromWebp($head),
            default => null,
        };

        return $profile !== null && self::describesRgb($profile) ? $profile : null;
    }

    /**
     * Whether a profile is plain sRGB, which every browser assumes for an
     * untagged image and so is not worth the bytes of embedding.
     */
    public static function isSrgb(string $profile): bool
    {
        foreach (self::SRGB_COLORANTS as $tag => $expected) {
            $actual = self::colorant($profile, $tag);

            if ($actual === null) {
                return false;
            }

            foreach ($expected as $i => $value) {
                if (abs($actual[$i] - $value) > 0.005) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Writes a profile into a WebP that GD encoded.
     *
     * The ICCP chunk is only allowed in the extended format, so a simple
     * lossy or lossless file is first given the VP8X header that announces it.
     */
    public static function embedInWebp(string $webp, string $profile): string
    {
        if (! str_starts_with($webp, 'RIFF') || substr($webp, 8, 4) !== 'WEBP') {
            return $webp;
        }

        $chunks = substr($webp, 12);
        $fourcc = substr($chunks, 0, 4);
        $size = unpack('V', substr($chunks, 4, 4))[1];

        if ($fourcc === 'VP8X') {
            $header = substr($chunks, 8, 10);
            $header[0] = chr(ord($header[0]) | 0x20);
            $rest = substr($chunks, 8 + $size + ($size & 1));
        } else {
            $dimensions = self::bitstreamDimensions($fourcc, substr($chunks, 8, 30));

            if ($dimensions === null) {
                return $webp;
            }

            [$width, $height, $hasAlpha] = $dimensions;
            $header = chr(0x20 | ($hasAlpha ? 0x10 : 0))."\0\0\0"
                .substr(pack('V', $width - 1), 0, 3)
                .substr(pack('V', $height - 1), 0, 3);
            $rest = $chunks;
        }

        $iccp = 'ICCP'.pack('V', strlen($profile)).$profile.(strlen($profile) & 1 ? "\0" : '');
        $body = 'WEBP'.'VP8X'.pack('V', 10).$header.$iccp.$rest;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }

    /**
     * A JPEG splits its profile over numbered APP2 segments of up to 64KB.
     */
    private static function fromJpeg(string $bytes): ?string
    {
        $parts = [];
        $offset = 2;
        $length = strlen($bytes);

        while ($offset + 4 <= $length && $bytes[$offset] === "\xFF") {
            $marker = ord($bytes[$offset + 1]);

            if ($marker === 0xFF) {
                $offset++;

                continue;
            }

            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            $segmentLength = unpack('n', substr($bytes, $offset + 2, 2))[1];

            if ($marker === 0xE2 && substr($bytes, $offset + 4, 12) === "ICC_PROFILE\0") {
                $parts[ord($bytes[$offset + 16])] = substr($bytes, $offset + 18, $segmentLength - 16);
            }

            $offset += 2 + $segmentLength;
        }

        if ($parts === []) {
            return null;
        }

        ksort($parts);

        return implode('', $parts);
    }

    /**
     * A PNG keeps its profile zlib-compressed in an iCCP chunk, after a name.
     */
    private static function fromPng(string $bytes): ?string
    {
        $offset = 8;

        while ($offset + 8 <= strlen($bytes)) {
            $chunkLength = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);

            if ($type === 'IDAT' || $type === 'IEND') {
                return null;
            }

            if ($type === 'iCCP') {
                $data = substr($bytes, $offset + 8, $chunkLength);
                $nameEnd = strpos($data, "\0");

                if ($nameEnd === false) {
                    return null;
                }

                $profile = @gzuncompress(substr($data, $nameEnd + 2));

                return is_string($profile) ? $profile : null;
            }

            $offset += 12 + $chunkLength;
        }

        return null;
    }

    private static function fromWebp(string $bytes): ?string
    {
        $offset = 12;

        while ($offset + 8 <= strlen($bytes)) {
            $fourcc = substr($bytes, $offset, 4);
            $size = unpack('V', substr($bytes, $offset + 4, 4))[1];

            if ($fourcc === 'ICCP') {
                return substr($bytes, $offset + 8, $size);
            }

            $offset += 8 + $size + ($size & 1);
        }

        return null;
    }

    /**
     * Only an RGB profile can describe the pixels GD hands back; a CMYK or
     * greyscale one would recolour the image instead.
     */
    private static function describesRgb(string $profile): bool
    {
        return strlen($profile) >= 132 && substr($profile, 16, 4) === 'RGB ';
    }

    /**
     * @return array{float, float, float}|null
     */
    private static function colorant(string $profile, string $tag): ?array
    {
        if (strlen($profile) < 132) {
            return null;
        }

        $count = unpack('N', substr($profile, 128, 4))[1];

        for ($i = 0; $i < $count; $i++) {
            $entry = substr($profile, 132 + $i * 12, 12);

            if (strlen($entry) < 12 || substr($entry, 0, 4) !== $tag) {
                continue;
            }

            $offset = unpack('N', substr($entry, 4, 4))[1];
            $data = substr($profile, $offset, 20);

            if (strlen($data) < 20 || substr($data, 0, 4) !== 'XYZ ') {
                return null;
            }

            $values = array_values(unpack('N3', substr($data, 8, 12)));

            return array_map(
                fn (int $value): float => ($value >= 0x80000000 ? $value - 0x100000000 : $value) / 65536,
                $values,
            );
        }

        return null;
    }

    /**
     * Reads the canvas size from the start of a simple-format bitstream.
     *
     * @return array{int, int, bool}|null Width, height, and whether it has alpha
     */
    private static function bitstreamDimensions(string $fourcc, string $bitstream): ?array
    {
        if ($fourcc === 'VP8 ' && strlen($bitstream) >= 10 && substr($bitstream, 3, 3) === "\x9D\x01\x2A") {
            $size = unpack('v2', substr($bitstream, 6, 4));

            return [$size[1] & 0x3FFF, $size[2] & 0x3FFF, false];
        }

        if ($fourcc === 'VP8L' && strlen($bitstream) >= 5 && $bitstream[0] === "\x2F") {
            $bits = unpack('V', substr($bitstream, 1, 4))[1];

            return [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1, (bool) (($bits >> 28) & 1)];
        }

        return null;
    }
}
