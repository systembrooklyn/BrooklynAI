<?php

return [
    'google_gmail' => [
        'name' => 'Gmail',
        'description' => 'إرسال واستقبال وإدارة البريد الإلكتروني عبر Google Gmail.',
        'actions' => [
            'send_email' => [
                'label' => 'إرسال بريد إلكتروني',
                'description' => 'إرسال رسالة بريد إلكتروني جديدة.',
                'fields' => [
                    'to' => ['label' => 'إلى'],
                    'subject' => ['label' => 'الموضوع'],
                    'body' => ['label' => 'المحتوى'],
                ],
            ],
            'reply_to_email' => [
                'label' => 'الرد على البريد الإلكتروني',
                'description' => 'الرد على سلسلة بريد إلكتروني موجودة.',
                'fields' => [
                    'to' => [
                        'label' => 'إلى',
                        'description' => 'عنوان بريد المستلم. عادةً مرسل البريد الأصلي: {{ trigger.from }}.',
                    ],
                    'subject' => [
                        'label' => 'الموضوع',
                        'description' => 'موضوع الرد. عادةً "Re: " متبوعًا بالموضوع الأصلي: Re: {{ trigger.subject }}.',
                    ],
                    'body' => ['label' => 'المحتوى', 'description' => 'نص الرد.'],
                    'thread_id' => [
                        'label' => 'معرّف السلسلة',
                        'description' => 'معرّف سلسلة Gmail. عادةً {{ trigger.thread_id }}.',
                    ],
                ],
            ],
            'create_draft' => [
                'label' => 'إنشاء مسودة',
                'description' => 'إنشاء مسودة بريد إلكتروني جديدة دون إرسالها.',
                'fields' => [
                    'to' => ['label' => 'إلى'],
                    'subject' => ['label' => 'الموضوع'],
                    'body' => ['label' => 'المحتوى'],
                ],
            ],
            'mark_as_read' => [
                'label' => 'وضع علامة مقروء',
                'description' => 'وضع علامة مقروء على رسالة بريد إلكتروني.',
                'fields' => [
                    'message_id' => [
                        'label' => 'معرّف الرسالة',
                        'description' => 'معرّف رسالة Gmail. عادةً {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'mark_as_unread' => [
                'label' => 'وضع علامة غير مقروء',
                'description' => 'وضع علامة غير مقروء على رسالة بريد إلكتروني.',
                'fields' => [
                    'message_id' => [
                        'label' => 'معرّف الرسالة',
                        'description' => 'معرّف رسالة Gmail. عادةً {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'archive' => [
                'label' => 'أرشفة البريد',
                'description' => 'إزالة رسالة بريد إلكتروني من صندوق الوارد.',
                'fields' => [
                    'message_id' => [
                        'label' => 'معرّف الرسالة',
                        'description' => 'معرّف رسالة Gmail. عادةً {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'trash' => [
                'label' => 'نقل إلى المهملات',
                'description' => 'نقل رسالة بريد إلكتروني إلى المهملات.',
                'fields' => [
                    'message_id' => [
                        'label' => 'معرّف الرسالة',
                        'description' => 'معرّف رسالة Gmail. عادةً {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'add_label' => [
                'label' => 'إضافة تسمية',
                'description' => 'إضافة تسمية Gmail إلى رسالة بريد إلكتروني.',
                'fields' => [
                    'message_id' => [
                        'label' => 'معرّف الرسالة',
                        'description' => 'معرّف رسالة Gmail. عادةً {{ trigger.message_id }}.',
                    ],
                    'label_id' => [
                        'label' => 'التسمية',
                        'description' => 'معرّف تسمية Gmail المراد إضافتها.',
                    ],
                ],
            ],
            'remove_label' => [
                'label' => 'إزالة تسمية',
                'description' => 'إزالة تسمية Gmail من رسالة بريد إلكتروني.',
                'fields' => [
                    'message_id' => [
                        'label' => 'معرّف الرسالة',
                        'description' => 'معرّف رسالة Gmail. عادةً {{ trigger.message_id }}.',
                    ],
                    'label_id' => [
                        'label' => 'التسمية',
                        'description' => 'معرّف تسمية Gmail المراد إزالتها.',
                    ],
                ],
            ],
            'create_label' => [
                'label' => 'إنشاء تسمية',
                'description' => 'إنشاء تسمية Gmail جديدة.',
                'fields' => [
                    'name' => [
                        'label' => 'اسم التسمية',
                        'description' => 'اسم تسمية Gmail الجديدة.',
                    ],
                ],
            ],
        ],
        'triggers' => [
            'new_email_received' => [
                'label' => 'استلام بريد إلكتروني جديد',
                'description' => 'يُشغَّل عند استلام بريد إلكتروني جديد.',
                'fields' => [
                    'label_id' => [
                        'label' => 'التسمية',
                        'description' => 'يُشغَّل فقط للرسائل التي تحمل تسمية Gmail هذه. اتركه فارغًا لمطابقة كل البريد.',
                    ],
                    'from' => [
                        'label' => 'من',
                        'description' => 'يُشغَّل فقط للرسائل التي يطابق مرسلها هذا المرشح. يستخدم صيغة بحث Gmail (مثال user@example.com).',
                    ],
                    'subject' => [
                        'label' => 'الموضوع',
                        'description' => 'يُشغَّل فقط للرسائل التي يطابق موضوعها هذا المرشح. تُطابَق القيم متعددة الكلمات كعبارة واحدة.',
                    ],
                    'has_attachment' => [
                        'label' => 'يحتوي على مرفق',
                        'description' => 'يُشغَّل فقط للرسائل التي تحمل مرفقًا.',
                    ],
                    'query' => [
                        'label' => 'استعلام متقدم',
                        'description' => 'استعلام بحث Gmail خام اختياري، مثال "is:unread larger:5M". للمستخدمين المتقدمين فقط.',
                    ],
                ],
            ],
        ],
    ],
    'google_calendar' => [
        'name' => 'تقويم Google',
        'description' => 'إدارة الأحداث والتقويمات والمدعوين عبر تقويم Google.',
        'actions' => [
            'create_event' => [
                'label' => 'إنشاء حدث',
                'description' => 'إنشاء حدث جديد في التقويم الأساسي.',
            ],
            'update_event' => [
                'label' => 'تحديث حدث',
                'description' => 'تحديث حدث موجود في التقويم الأساسي.',
            ],
            'delete_event' => [
                'label' => 'حذف حدث',
                'description' => 'حذف حدث من التقويم الأساسي.',
            ],
            'list_events' => [
                'label' => 'سرد الأحداث',
                'description' => 'سرد الأحداث القادمة من التقويم الأساسي.',
            ],
            'get_event' => [
                'label' => 'استرجاع حدث',
                'description' => 'استرجاع حدث معيّن من التقويم الأساسي.',
            ],
        ],
    ],
    'google_sheets' => [
        'name' => 'جداول Google',
        'description' => 'قراءة وكتابة وإدارة بيانات الجداول عبر جداول Google.',
        'actions' => [
            'list_spreadsheets' => [
                'label' => 'سرد الجداول',
                'description' => 'سرد الجداول المتاحة للحساب المتصل.',
            ],
            'get_spreadsheet' => [
                'label' => 'استرجاع الجدول',
                'description' => 'استرجاع جدول وأوراقه/تبويباته.',
            ],
            'add_sheet' => [
                'label' => 'إضافة ورقة',
                'description' => 'إضافة ورقة/تبويب جديد إلى الجدول.',
            ],
            'delete_sheet' => [
                'label' => 'حذف ورقة',
                'description' => 'حذف ورقة/تبويب من الجدول.',
            ],
            'get_data' => [
                'label' => 'قراءة البيانات',
                'description' => 'قراءة القيم من نطاق في الجدول.',
            ],
            'update_data' => [
                'label' => 'تحديث البيانات',
                'description' => 'تحديث القيم في نطاق بالجدول.',
            ],
            'append_data' => [
                'label' => 'إضافة بيانات',
                'description' => 'إضافة قيم إلى نطاق في الجدول.',
            ],
            'clear_data' => [
                'label' => 'مسح البيانات',
                'description' => 'مسح القيم في نطاق بالجدول.',
            ],
            'append_row_by_headers' => [
                'label' => 'إضافة صف بالعناوين',
                'description' => 'إضافة صف جديد مُعيَّن بأسماء العناوين.',
            ],
        ],
    ],
    'google_docs' => [
        'name' => 'مستندات Google',
        'description' => 'إنشاء وتحرير المستندات عبر مستندات Google.',
        'actions' => [
            'create_document' => [
                'label' => 'إنشاء مستند',
                'description' => 'إنشاء مستند Google جديد.',
            ],
            'get_document' => [
                'label' => 'استرجاع المستند',
                'description' => 'استرجاع محتوى وبيانات مستند Google.',
            ],
            'append_text' => [
                'label' => 'إضافة نص',
                'description' => 'إضافة نص إلى نهاية مستند Google.',
            ],
            'update_document' => [
                'label' => 'تحديث المستند',
                'description' => 'استبدال كامل محتوى مستند Google.',
            ],
            'delete_document' => [
                'label' => 'حذف المستند',
                'description' => 'نقل مستند Google إلى المهملات.',
            ],
            'download_pdf' => [
                'label' => 'تنزيل بصيغة PDF',
                'description' => 'تصدير مستند Google بصيغة PDF.',
            ],
            'generate_from_template' => [
                'label' => 'إنشاء من قالب',
                'description' => 'إنشاء مستند جديد بنسخ قالب واستبدال العناصر النائبة.',
            ],
        ],
    ],
    'google_analytics' => [
        'name' => 'تحليلات Google',
        'description' => 'الوصول إلى بيانات وتقارير تحليلات Google.',
        'actions' => [
            'list_properties' => [
                'label' => 'سرد الخصائص',
                'description' => 'سرد خصائص GA4 المتاحة للحساب المتصل.',
            ],
            'get_report' => [
                'label' => 'استرجاع تقرير',
                'description' => 'تشغيل تقرير لخاصية GA4.',
            ],
            'get_realtime_overview' => [
                'label' => 'النظرة الفورية',
                'description' => 'جلب مقاييس فورية لخاصية GA4.',
            ],
            'get_home_screen_metrics' => [
                'label' => 'مقاييس الشاشة الرئيسية',
                'description' => 'جلب مقاييس ملخّصة للشاشة الرئيسية لخاصية GA4.',
            ],
            'get_top_pages_by_views' => [
                'label' => 'أعلى الصفحات بالمشاهدات',
                'description' => 'جلب أعلى الصفحات حسب مشاهدات الصفحة لخاصية GA4.',
            ],
        ],
    ],
    'google_drive' => [
        'name' => 'Google Drive',
        'description' => 'إدارة الملفات والمجلدات في Google Drive.',
    ],
];
