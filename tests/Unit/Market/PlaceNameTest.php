<?php

use App\Services\Market\PlaceName;

it('gives two spellings of one place the same key', function (string $one, string $other) {
    expect(PlaceName::key($one))->toBe(PlaceName::key($other));
})->with([
    'hamza' => ['أم السلم', 'ام السلم'],
    'alef maqsura' => ['الصوارى', 'الصواري'],
    'ta marbuta' => ['مكة المكرمة', 'مكه المكرمه'],
    'spacing' => ['  الأمير   فواز ', 'الامير فواز'],
]);

it('keeps different places apart', function () {
    expect(PlaceName::key('الصفا'))->not->toBe(PlaceName::key('الصفاء'));
});

it('drops the word region whichever way it is written', function (string $written, string $expected) {
    expect(PlaceName::region($written))->toBe($expected);
})->with([
    ['منطقة مكة المكرمه', 'مكة المكرمه'],
    ['المنطقة الشرقية', 'الشرقية'],
    ['منطقة الشرقية', 'الشرقية'],
    ['الرياض', 'الرياض'],
]);

it('takes the district out of the pair it is stored in', function (string $written, string $city, string $expected) {
    expect(PlaceName::district($written, $city))->toBe($expected);
})->with([
    'city prefix' => ['جده/ الصفا ', 'جده', 'الصفا'],
    'bare prefix' => ['حي/المروة', 'جده', 'المروة'],
    'city spelt another way' => ['جدة/ الصفا', 'جده', 'الصفا'],
    'the word district in front' => ['جده/ حى الأمير فواز الجنوبى ', 'جده', 'الأمير فواز الجنوبى'],
    'a slash of its own' => ['حي/الزهراء/ 2', 'جده', 'الزهراء/ 2'],
    'no prefix at all' => ['الزهراء/ 2', 'جده', 'الزهراء/ 2'],
    'nothing' => ['', 'جده', ''],
]);

it('shows the usual spelling of the places written unusually', function () {
    expect(PlaceName::preferred('جده'))->toBe('جدة')
        ->and(PlaceName::preferred('مكة المكرمه'))->toBe('مكة المكرمة')
        ->and(PlaceName::preferred('الصفا'))->toBe('الصفا');
});

it('matches one publisher\'s spelling of a place against another\'s', function (string $one, string $other) {
    expect(PlaceName::loose($one))->toBe(PlaceName::loose($other));
})->with([
    ['الصفا', 'الصفاء'],
    ['الأمير عبد المجيد', 'الامير عبدالمجيد'],
]);

it('still tells unlike places apart when matching loosely', function () {
    expect(PlaceName::loose('الصفا'))->not->toBe(PlaceName::loose('الصالحية'))
        // Loose matching is for looking a place up, never for grouping one file.
        ->and(PlaceName::key('الصفا'))->not->toBe(PlaceName::key('الصفاء'));
});
