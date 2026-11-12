<?php

namespace App\Services\Market;

/**
 * Reconciles the several ways the ministry's files spell one place.
 *
 * The same district arrives as «أم السلم» and «ام السلم», the same region as
 * «منطقة مكة المكرمه» and «منطقة مكة المكرمة». A key folds those together so a
 * place keeps one history; the label is what a person reads.
 */
class PlaceName
{
    /**
     * Spellings shown in place of the ministry's own, by key.
     *
     * @var array<string, string>
     */
    private const PREFERRED = [
        'جده' => 'جدة',
        'مكه المكرمه' => 'مكة المكرمة',
        'المدينه المنوره' => 'المدينة المنورة',
        'الشرقيه' => 'الشرقية',
        'الحدود الشماليه' => 'الحدود الشمالية',
        'الباحه' => 'الباحة',
    ];

    /**
     * The name as written, without stray spacing.
     */
    public static function label(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * The form two spellings of one place share.
     */
    public static function key(string $name): string
    {
        return str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ى', 'ة', 'ـ'],
            ['ا', 'ا', 'ا', 'ا', 'ي', 'ه', ''],
            self::label($name),
        );
    }

    /**
     * A looser form, for matching one publisher's name for a place against
     * another's. The ministry writes «الصفا» and «الأمير عبد المجيد» where
     * the authority writes «الصفاء» and «الأمير عبدالمجيد»: spacing and a
     * closing hamza are dropped. Too loose to group a single file by, where
     * two districts could differ by exactly that, so it is used only across
     * sources.
     */
    public static function loose(string $name): string
    {
        return rtrim(str_replace(' ', '', self::key($name)), 'ء');
    }

    /**
     * A region without the word «منطقة» some quarters put in front of it.
     */
    public static function region(string $name): string
    {
        return self::label((string) preg_replace('/^(المنطقة|منطقة)\s+/u', '', self::label($name)));
    }

    /**
     * A district out of the «city/district» pair the files store it in.
     *
     * The prefix is dropped only when it names the city or is the bare word
     * «حي», so a district whose own name holds a slash keeps all of it.
     */
    public static function district(string $name, string $city): string
    {
        $parts = explode('/', $name, 2);

        if (count($parts) === 2 && in_array(self::key($parts[0]), [self::key($city), 'حي'], true)) {
            $name = $parts[1];
        }

        return self::label((string) preg_replace('/^(حي|حى)\s+/u', '', self::label($name)));
    }

    /**
     * The spelling to show for a label, where the ministry's is not the usual one.
     */
    public static function preferred(string $label): string
    {
        return self::PREFERRED[self::key($label)] ?? $label;
    }
}
