<?php

use App\Enums\AppointmentStatus;

it('permite confirmar apenas a partir de pending', function () {
    expect(AppointmentStatus::Pending->allows('confirm'))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->allows('confirm'))->toBeFalse()
        ->and(AppointmentStatus::Cancelled->allows('confirm'))->toBeFalse()
        ->and(AppointmentStatus::Completed->allows('confirm'))->toBeFalse()
        ->and(AppointmentStatus::NoShow->allows('confirm'))->toBeFalse();
});

it('permite cancelar a partir de pending ou confirmed', function () {
    expect(AppointmentStatus::Pending->allows('cancel'))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->allows('cancel'))->toBeTrue()
        ->and(AppointmentStatus::Cancelled->allows('cancel'))->toBeFalse()
        ->and(AppointmentStatus::Completed->allows('cancel'))->toBeFalse()
        ->and(AppointmentStatus::NoShow->allows('cancel'))->toBeFalse();
});

it('permite completar a partir de pending ou confirmed', function () {
    expect(AppointmentStatus::Pending->allows('complete'))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->allows('complete'))->toBeTrue()
        ->and(AppointmentStatus::Completed->allows('complete'))->toBeFalse();
});

it('permite marcar falta a partir de pending ou confirmed', function () {
    expect(AppointmentStatus::Pending->allows('no_show'))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->allows('no_show'))->toBeTrue()
        ->and(AppointmentStatus::NoShow->allows('no_show'))->toBeFalse();
});

it('permite remarcar a partir de pending ou confirmed', function () {
    expect(AppointmentStatus::Pending->allows('reschedule'))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->allows('reschedule'))->toBeTrue()
        ->and(AppointmentStatus::Cancelled->allows('reschedule'))->toBeFalse()
        ->and(AppointmentStatus::Completed->allows('reschedule'))->toBeFalse()
        ->and(AppointmentStatus::NoShow->allows('reschedule'))->toBeFalse();
});

it('nao permite nenhuma transicao a partir de completed', function () {
    expect(AppointmentStatus::Completed->allows('confirm'))->toBeFalse()
        ->and(AppointmentStatus::Completed->allows('cancel'))->toBeFalse()
        ->and(AppointmentStatus::Completed->allows('complete'))->toBeFalse()
        ->and(AppointmentStatus::Completed->allows('no_show'))->toBeFalse()
        ->and(AppointmentStatus::Completed->allows('reschedule'))->toBeFalse();
});

it('ignora acao desconhecida com seguranca', function () {
    expect(AppointmentStatus::Pending->allows('archive'))->toBeFalse();
});
