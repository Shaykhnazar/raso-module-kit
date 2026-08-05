<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

enum ConsumeOutcome: string
{
    /** Qayta ishlandi. */
    case Processed = 'processed';

    /** Allaqachon ko'rilgan — at-least-once da NORMAL holat, xato emas. */
    case Duplicate = 'duplicate';

    /** Bu modulda bu hodisaga handler yo'q. */
    case Ignored = 'ignored';

    /** Qayta ishlashda xato — DLQ'ga yozildi, tranzaksiya qaytarildi. */
    case Failed = 'failed';
}
