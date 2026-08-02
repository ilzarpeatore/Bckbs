<?php

return [
    'accepted' => 'يجب قبول :attribute.',
    'active_url' => ' :attribute ليس عنوان URL صالحًا.',
    'after' => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا بعد أو يساوي :date.',
    'alpha' => 'قد يحتوي :attribute على أحرف فقط.',
    'alpha_dash' => 'قد يحتوي :attribute فقط على أحرف وأرقام وشرطات وشرطات سفلية.',
    'alpha_num' => 'قد يحتوي :attribute على أحرف وأرقام فقط.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'before' => 'يجب أن يكون :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخًا قبل أو يساوي :date.',
    'between' => [
        'numeric' => 'يجب أن يكون :attribute بين :min و :max.',
        'file' => 'يجب أن يتراوح حجم :attribute بين :min و :max كيلو بايت.',
        'string' => 'يجب أن يكون :attribute بين :min و :max حرفًا.',
        'array' => 'يجب أن يحتوي :attribute على عناصر بين :min و :max.',
    ],
    'boolean' => 'يجب أن يكون الحقل :attribute صحيحًا أو خطأ.',

	'confirmed' => 'التأكيد :attribute غير متطابق.',
    'date' => ' :attribute ليس تاريخًا صالحًا.',
    'date_equals' => 'يجب أن يكون :attribute تاريخًا يساوي :date.',
    'date_format' => ' :attribute لا يتطابق مع التنسيق :format.',
    'different' => 'يجب أن يكون :attribute و :other مختلفين.',
    'digits' => 'يجب أن يكون :attribute رقمين :digits.',
    'digits_between' => 'يجب أن يكون :attribute بين أرقام :min و :max.',
    'dimensions' => 'يحتوي :attribute على أبعاد صورة غير صالحة.',
    'distinct' => 'يحتوي الحقل :attribute على قيمة مكررة.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحًا.',
    'ends_with' => 'يجب أن ينتهي :attribute بواحد مما يلي: :values.',
    'exists' => ' :attribute المحدد غير صالح.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'filled' => 'يجب أن يحتوي الحقل :attribute على قيمة.',
    'gt' => [
        'numeric' => 'يجب أن يكون :attribute أكبر من :value.',
        'file' => 'يجب أن يكون :attribute أكبر من :value كيلو بايت.',
        'string' => 'يجب أن يكون :attribute أكبر من :value حرفًا.',
        'array' => 'يجب أن يحتوي :attribute على أكثر من :value عنصر.',
    ],

    'gte' => [
        'numeric' => 'يجب أن يكون :attribute أكبر من أو يساوي :value.',
        'file' => 'يجب أن يكون :attribute أكبر من أو يساوي :value كيلو بايت.',
        'string' => 'يجب أن يكون :attribute أكبر من أو يساوي :value من الأحرف.',
        'array' => 'يجب أن يحتوي :attribute على :value من العناصر أو أكثر.',
    ],
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => ' :attribute المحدد غير صالح.',
    'in_array' => 'الحقل :attribute غير موجود في :other.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'ip' => 'يجب أن يكون :attribute عنوان IP صالحًا.',
    'ipv4' => 'يجب أن يكون :attribute عنوان IPv4 صالحًا.',
    'ipv6' => 'يجب أن يكون :attribute عنوان IPv6 صالحًا.',
    'json' => 'يجب أن يكون :attribute سلسلة JSON صالحة.',
    'lt' => [
        'numeric' => 'يجب أن يكون :attribute أقل من :value.',
        'file' => 'يجب أن يكون :attribute أقل من :value كيلو بايت.',
        'string' => 'يجب أن يكون :attribute أقل من :value حرفًا.',
        'array' => 'يجب أن يحتوي :attribute على عناصر أقل من :value.',
    ],
    'lte' => [
        'numeric' => 'يجب أن يكون :attribute أقل من أو يساوي :value.',
        'file' => 'يجب أن يكون :attribute أقل من أو يساوي :value كيلو بايت.',
        'string' => 'يجب أن يكون :attribute أقل من أو يساوي :value من الأحرف.',
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :value من العناصر.',
    ],

    'max' => [
		'numeric' => 'لا يجوز أن يكون :attribute أكبر من :max.',
		'file' => 'قد لا يكون :attribute أكبر من :max كيلو بايت.',
		'string' => 'لا يجوز أن يكون :attribute أكبر من :max حرفًا.',
		'array' => 'قد لا يحتوي :attribute على أكثر من :max عنصر.',
    ],

	'mimes' => 'يجب أن يكون ملف :attribute ملفًا من النوع: :values.',
	'mimetypes' => 'يجب أن يكون ملف :attribute ملفًا من النوع: :values.',
	'min' => [
		'numeric' => 'يجب أن يكون :attribute على الأقل :min.',
		'file' => 'يجب أن يكون حجم :attribute على الأقل :min كيلو بايت.',
		'string' => 'يجب أن يتكون :attribute من حرفين :min على الأقل.',
		'array' => 'يجب أن يحتوي :attribute على عناصر :min على الأقل.',
	],
	'multiple_of' => 'يجب أن يكون :attribute مضاعفًا لـ :value.',
	'not_in' => ' :attribute المحدد غير صالح.',
	'not_regex' => 'تنسيق :attribute غير صالح.',
	'numeric' => 'يجب أن يكون :attribute رقمًا.',
	'password' => 'كلمة المرور غير صحيحة.',
	'present' => 'يجب أن يكون الحقل :attribute موجودًا.',
	'regex' => 'تنسيق :attribute غير صالح.',
	'required' => 'الحقل :attribute مطلوب.',

	'required_if' => 'الحقل :attribute مطلوب عندما يكون :other يساوي :value.',
	'required_unless' => 'الحقل :attribute مطلوب ما لم يكن :other موجودًا في :values.',
	'required_with' => 'الحقل :attribute مطلوب عند وجود :values.',
	'required_with_all' => 'الحقل :attribute مطلوب عند وجود :values.',
	'required_without' => 'الحقل :attribute مطلوب في حالة عدم وجود :values.',
	'required_without_all' => 'الحقل :attribute مطلوب في حالة عدم وجود :values.',
	'same' => 'يجب أن يتطابق :attribute و :other.',
	'size' => [
		'numeric' => 'يجب أن يكون :attribute :size.',
		'file' => 'يجب أن يكون :attribute :size كيلو بايت.',
		'string' => 'يجب أن يتكون :attribute من حرفين :size.',
		'array' => 'يجب أن يحتوي :attribute على عناصر :size.',
	],
	'starts_with' => 'يجب أن يبدأ :attribute بأحد الإجراءات التالية: :values.',
	'string' => 'يجب أن يكون :attribute عبارة عن سلسلة.',
	'timezone' => 'يجب أن تكون :attribute منطقة صالحة.',
	'unique' => 'لقد تم بالفعل أخذ :attribute.',
	'uploaded' => 'فشل تحميل :attribute.',
	'url' => 'تنسيق :attribute غير صالح.',
	'uuid' => 'يجب أن يكون :attribute معرف UUID صالحًا.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],
    'attributes' => [],
];
