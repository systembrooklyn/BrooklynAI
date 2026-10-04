<?php

return [
    'google_gmail' => [
        'name' => 'Gmail',
        'description' => 'Send, receive, and manage emails through Google Gmail.',
        'actions' => [
            'send_email' => [
                'label' => 'Send Email',
                'description' => 'Send a new email message.',
                'fields' => [
                    'to' => ['label' => 'To'],
                    'subject' => ['label' => 'Subject'],
                    'body' => ['label' => 'Body'],
                ],
            ],
            'reply_to_email' => [
                'label' => 'Reply to Email',
                'description' => 'Reply to an existing email thread.',
                'fields' => [
                    'to' => [
                        'label' => 'To',
                        'description' => 'Recipient email address. Typically the sender of the original email: {{ trigger.from }}.',
                    ],
                    'subject' => [
                        'label' => 'Subject',
                        'description' => 'Reply subject. Typically "Re: " followed by the original subject: Re: {{ trigger.subject }}.',
                    ],
                    'body' => ['label' => 'Body', 'description' => 'Reply body.'],
                    'thread_id' => [
                        'label' => 'Thread ID',
                        'description' => 'Gmail thread ID. Typically {{ trigger.thread_id }}.',
                    ],
                ],
            ],
            'create_draft' => [
                'label' => 'Create Draft',
                'description' => 'Create a new email draft without sending it.',
                'fields' => [
                    'to' => ['label' => 'To'],
                    'subject' => ['label' => 'Subject'],
                    'body' => ['label' => 'Body'],
                ],
            ],
            'mark_as_read' => [
                'label' => 'Mark as Read',
                'description' => 'Mark an email message as read.',
                'fields' => [
                    'message_id' => [
                        'label' => 'Message ID',
                        'description' => 'Gmail message ID. Typically {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'mark_as_unread' => [
                'label' => 'Mark as Unread',
                'description' => 'Mark an email message as unread.',
                'fields' => [
                    'message_id' => [
                        'label' => 'Message ID',
                        'description' => 'Gmail message ID. Typically {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'archive' => [
                'label' => 'Archive Email',
                'description' => 'Remove an email message from the inbox.',
                'fields' => [
                    'message_id' => [
                        'label' => 'Message ID',
                        'description' => 'Gmail message ID. Typically {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'trash' => [
                'label' => 'Move to Trash',
                'description' => 'Move an email message to the trash.',
                'fields' => [
                    'message_id' => [
                        'label' => 'Message ID',
                        'description' => 'Gmail message ID. Typically {{ trigger.message_id }}.',
                    ],
                ],
            ],
            'add_label' => [
                'label' => 'Add Label',
                'description' => 'Add a Gmail label to an email message.',
                'fields' => [
                    'message_id' => [
                        'label' => 'Message ID',
                        'description' => 'Gmail message ID. Typically {{ trigger.message_id }}.',
                    ],
                    'label_id' => [
                        'label' => 'Label',
                        'description' => 'Gmail label ID to add.',
                    ],
                ],
            ],
            'remove_label' => [
                'label' => 'Remove Label',
                'description' => 'Remove a Gmail label from an email message.',
                'fields' => [
                    'message_id' => [
                        'label' => 'Message ID',
                        'description' => 'Gmail message ID. Typically {{ trigger.message_id }}.',
                    ],
                    'label_id' => [
                        'label' => 'Label',
                        'description' => 'Gmail label ID to remove.',
                    ],
                ],
            ],
            'create_label' => [
                'label' => 'Create Label',
                'description' => 'Create a new Gmail label.',
                'fields' => [
                    'name' => [
                        'label' => 'Label Name',
                        'description' => 'The name of the new Gmail label.',
                    ],
                ],
            ],
        ],
        'triggers' => [
            'new_email_received' => [
                'label' => 'New Email Received',
                'description' => 'Trigger when a new email is received.',
                'fields' => [
                    'label_id' => [
                        'label' => 'Label',
                        'description' => 'Only trigger for emails with this Gmail label. Leave empty to match all mail.',
                    ],
                    'from' => [
                        'label' => 'From',
                        'description' => 'Only trigger for emails whose sender matches this filter. Uses Gmail search syntax (for example user@example.com).',
                    ],
                    'subject' => [
                        'label' => 'Subject',
                        'description' => 'Only trigger for emails whose subject matches this filter. Multi-word values are matched as a phrase.',
                    ],
                    'has_attachment' => [
                        'label' => 'Has Attachment',
                        'description' => 'Only trigger for emails that carry an attachment.',
                    ],
                    'query' => [
                        'label' => 'Advanced Query',
                        'description' => 'Optional raw Gmail search query, for example "is:unread larger:5M". Advanced users only.',
                    ],
                ],
            ],
        ],
    ],
    'google_calendar' => [
        'name' => 'Google Calendar',
        'description' => 'Manage events, calendars, and attendees through Google Calendar.',
        'actions' => [
            'create_event' => [
                'label' => 'Create Event',
                'description' => 'Create a new event in the primary calendar.',
            ],
            'update_event' => [
                'label' => 'Update Event',
                'description' => 'Update an existing event in the primary calendar.',
            ],
            'delete_event' => [
                'label' => 'Delete Event',
                'description' => 'Delete an event from the primary calendar.',
            ],
            'list_events' => [
                'label' => 'List Events',
                'description' => 'List upcoming events from the primary calendar.',
            ],
            'get_event' => [
                'label' => 'Get Event',
                'description' => 'Retrieve a specific event from the primary calendar.',
            ],
        ],
    ],
    'google_sheets' => [
        'name' => 'Google Sheets',
        'description' => 'Read, write, and manage spreadsheet data through Google Sheets.',
        'actions' => [
            'list_spreadsheets' => [
                'label' => 'List Spreadsheets',
                'description' => 'List spreadsheets accessible to the connected account.',
            ],
            'get_spreadsheet' => [
                'label' => 'Get Spreadsheet',
                'description' => 'Retrieve a spreadsheet and its sheets/tabs.',
            ],
            'add_sheet' => [
                'label' => 'Add Sheet',
                'description' => 'Add a new sheet/tab to a spreadsheet.',
            ],
            'delete_sheet' => [
                'label' => 'Delete Sheet',
                'description' => 'Delete a sheet/tab from a spreadsheet.',
            ],
            'get_data' => [
                'label' => 'Get Data',
                'description' => 'Read values from a range in a spreadsheet.',
            ],
            'update_data' => [
                'label' => 'Update Data',
                'description' => 'Update values in a range in a spreadsheet.',
            ],
            'append_data' => [
                'label' => 'Append Data',
                'description' => 'Append values to a range in a spreadsheet.',
            ],
            'clear_data' => [
                'label' => 'Clear Data',
                'description' => 'Clear values in a range in a spreadsheet.',
            ],
            'append_row_by_headers' => [
                'label' => 'Append Row By Headers',
                'description' => 'Append a new row mapped by header names.',
            ],
        ],
    ],
    'google_docs' => [
        'name' => 'Google Docs',
        'description' => 'Create and edit documents through Google Docs.',
        'actions' => [
            'create_document' => [
                'label' => 'Create Document',
                'description' => 'Create a new Google Doc.',
            ],
            'get_document' => [
                'label' => 'Get Document',
                'description' => 'Retrieve the content and metadata of a Google Doc.',
            ],
            'append_text' => [
                'label' => 'Append Text',
                'description' => 'Append text to the end of a Google Doc.',
            ],
            'update_document' => [
                'label' => 'Update Document',
                'description' => 'Replace the entire content of a Google Doc.',
            ],
            'delete_document' => [
                'label' => 'Delete Document',
                'description' => 'Move a Google Doc to trash.',
            ],
            'download_pdf' => [
                'label' => 'Download As PDF',
                'description' => 'Export a Google Doc as a PDF.',
            ],
            'generate_from_template' => [
                'label' => 'Generate From Template',
                'description' => 'Create a new document by copying a template and replacing placeholders.',
            ],
        ],
    ],
    'google_analytics' => [
        'name' => 'Google Analytics',
        'description' => 'Access Google Analytics data and reports.',
        'actions' => [
            'list_properties' => [
                'label' => 'List Properties',
                'description' => 'List GA4 properties accessible to the connected account.',
            ],
            'get_report' => [
                'label' => 'Get Report',
                'description' => 'Run a report for a GA4 property.',
            ],
            'get_realtime_overview' => [
                'label' => 'Get Realtime Overview',
                'description' => 'Fetch realtime metrics for a GA4 property.',
            ],
            'get_home_screen_metrics' => [
                'label' => 'Get Home Screen Metrics',
                'description' => 'Fetch summarized home-screen metrics for a GA4 property.',
            ],
            'get_top_pages_by_views' => [
                'label' => 'Get Top Pages By Views',
                'description' => 'Fetch top pages by page views for a GA4 property.',
            ],
        ],
    ],
    'google_drive' => [
        'name' => 'Google Drive',
        'description' => 'Manage files and folders in Google Drive.',
    ],
];
