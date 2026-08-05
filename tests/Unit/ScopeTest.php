<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\Scope;

it('bo\'shliq bilan ajratilgan satrdan quriladi', function (): void {
    $scope = Scope::fromString('openid profile chat:read chat:write');

    expect($scope->all())->toBe(['openid', 'profile', 'chat:read', 'chat:write']);
});

it('ortiqcha bo\'shliqni yutadi', function (): void {
    expect(Scope::fromString("  openid   profile\tchat:read \n")->all())
        ->toBe(['openid', 'profile', 'chat:read']);
});

it('takrorni olib tashlaydi va tartibni saqlaydi', function (): void {
    expect(Scope::fromString('chat:read openid chat:read')->all())
        ->toBe(['chat:read', 'openid']);
});

it('bo\'sh satrdan bo\'sh scope', function (): void {
    expect(Scope::fromString('')->all())->toBe([])
        ->and(Scope::fromString('   ')->isEmpty())->toBeTrue();
});

it('scope borligini aytadi', function (): void {
    $scope = Scope::fromString('openid chat:read');

    expect($scope->has('chat:read'))->toBeTrue()
        ->and($scope->has('chat:write'))->toBeFalse();
});

it('WILDCARD kengaytirmaydi — `chat:read` `chat:*` ni bermaydi', function (): void {
    // Ataylab: implicit kengayish ruxsatni jimgina kengaytiradigan klassik teshik.
    $scope = Scope::fromString('chat:read');

    expect($scope->has('chat'))->toBeFalse()
        ->and($scope->has('chat:*'))->toBeFalse()
        ->and($scope->has('chat:read:extra'))->toBeFalse();
});

it('prefiks mos kelishi YETARLI EMAS', function (): void {
    // `chat:read` mavjud bo'lsa `chat:read_all` ochilib ketmasin.
    expect(Scope::fromString('chat:read')->has('chat:read_all'))->toBeFalse();
});

it('hamma talab qilingan scope borligini tekshiradi', function (): void {
    $scope = Scope::fromString('openid profile chat:read');

    expect($scope->hasAll(['openid', 'chat:read']))->toBeTrue()
        ->and($scope->hasAll(['openid', 'chat:write']))->toBeFalse()
        ->and($scope->hasAll([]))->toBeTrue();
});

it('satrga qaytadi', function (): void {
    expect((string) Scope::fromString('openid  chat:read'))->toBe('openid chat:read');
});

it('scope nomi registrga SEZGIR', function (): void {
    // OAuth 2.0 (RFC 6749 §3.3) scope qiymati case-sensitive.
    expect(Scope::fromString('Chat:Read')->has('chat:read'))->toBeFalse();
});
