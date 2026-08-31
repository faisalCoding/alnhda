<?php

namespace App\Livewire;

use App\Models\Visitor;
use Livewire\Component;

/**
 * The contact flow behind the button on the front of the site.
 *
 * It asks who the visitor is before it asks how to reach them: a buyer, an
 * agent and someone proposing a partnership all reach the same inbox, and the
 * answer is worth far more than the phone number on its own.
 *
 * The steps themselves are driven by Alpine in the view — walking back and
 * forth between three questions is not worth a network round trip each way —
 * and only the finished answer is sent here.
 */
class ContactWizard extends Component
{
    public string $role = '';

    public string $unitType = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $email = '';

    /**
     * Who the visitor says they are, and what each answer means for what is
     * asked next. A buyer is asked what they are looking for; the other two
     * have nothing more to choose.
     *
     * @var array<string, array{label: string, hint: string, asksUnitType: bool}>
     */
    public const ROLES = [
        'client' => [
            'label' => 'عميل أبحث عن مسكن',
            'hint' => 'أبحث عن فيلا أو دور أو شقة للشراء',
            'asksUnitType' => true,
        ],
        'marketer' => [
            'label' => 'مسوّق عقاري',
            'hint' => 'أعمل في التسويق العقاري وأرغب في تسويق وحداتكم',
            'asksUnitType' => false,
        ],
        'partnership' => [
            'label' => 'أبحث عن فرصة شراكة',
            'hint' => 'لديّ أرض أو رأس مال وأبحث عن شراكة تطوير',
            'asksUnitType' => false,
        ],
    ];

    /**
     * @var array<string, string>
     */
    public const UNIT_TYPES = [
        'villa' => 'فلل',
        'floor' => 'أدوار',
        'apartment' => 'شقق',
    ];

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function rules(): array
    {
        return [
            'role' => 'required|in:'.implode(',', array_keys(self::ROLES)),
            // Only a buyer is asked this, so it is required exactly when it was asked.
            'unitType' => ($this->asksUnitType() ? 'required|' : 'nullable|').'in:'.implode(',', array_keys(self::UNIT_TYPES)),
            'first_name' => 'required|string|min:2|max:60',
            'last_name' => 'required|string|min:2|max:60',
            // Kept permissive on purpose: a rule that rejects a reachable
            // number loses the enquiry the whole flow exists to collect.
            'phone' => ['required', 'string', 'regex:/^[\d\s+\-()]{9,20}$/'],
            'email' => 'nullable|email|max:120',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'role.required' => 'اختر صفتك أولًا.',
            'unitType.required' => 'اختر نوع الوحدة التي تبحث عنها.',
            'first_name.required' => 'الاسم الأول مطلوب.',
            'first_name.min' => 'الاسم الأول قصير جدًا.',
            'last_name.required' => 'اسم العائلة مطلوب.',
            'last_name.min' => 'اسم العائلة قصير جدًا.',
            'phone.required' => 'رقم الجوال مطلوب.',
            'phone.regex' => 'تحقق من رقم الجوال — يقبل الأرقام والمسافات وعلامة + فقط.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
        ];
    }

    public function asksUnitType(): bool
    {
        return (bool) (self::ROLES[$this->role]['asksUnitType'] ?? false);
    }

    /**
     * The trail the enquiry came down, kept in one string because that is what
     * the visitors screen groups by.
     */
    public function formName(): string
    {
        return $this->asksUnitType() && $this->unitType !== ''
            ? 'hero_client_'.$this->unitType
            : 'hero_'.$this->role;
    }

    public function save(): void
    {
        $this->validate();

        Visitor::create([
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
            'phone' => trim($this->phone),
            'email' => $this->email === '' ? null : trim($this->email),
            'form_name' => $this->formName(),
        ]);

        $this->reset(['role', 'unitType', 'first_name', 'last_name', 'phone', 'email']);

        $this->dispatch('contact-wizard-saved');
    }

    public function render()
    {
        return view('livewire.contact-wizard');
    }
}
