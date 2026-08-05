<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/**
 * Core chiqaradigan hodisalarning BARQAROR nomlari.
 *
 * Shakli: `<context>.<hodisa_o'tgan_zamonda>`. Bu nomlar servislararo
 * shartnoma — o'zgartirilsa hamma modul buziladi. Qo'shish mumkin,
 * qayta nomlash MUMKIN EMAS.
 */
final class EventNames
{
    /**
     * ⚠️ Har modul buni eshitishi va ma'lumotni O'CHIRISHI **MAJBURIY** —
     * O'zR «Shaxsiy ma'lumotlar to'g'risida»gi qonuni talabi. Payload: `sub`.
     */
    public const string UserDeleted = 'identity.user_deleted';

    /** Ism/avatar/til o'zgardi. Kesh yangilanadi. Payload: `sub`, `name`, `picture`, `locale`. */
    public const string UserProfileUpdated = 'identity.user_profile_updated';

    /** Foydalanuvchi bloklandi — modul uni darhol to'sishi kerak. Payload: `sub`. */
    public const string UserBanned = 'identity.user_banned';

    /** Modul o'chirildi — ma'lumot ARXIVLANADI (o'chirilmaydi). Payload: `sub`, `module`. */
    public const string ModuleDisabled = 'catalog.module_disabled';
}
