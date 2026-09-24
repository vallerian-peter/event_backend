<?php

use App\Jobs\GenerateInvitationCardJob;
use App\Jobs\ImportInviteesJob;
use App\Jobs\SendEmailJob;
use App\Jobs\SendSmsJob;
use App\Jobs\SendWhatsappJob;
use App\Services\Messaging\BeemSmsService;
use App\Services\Messaging\BrevoEmailService;
use App\Services\Messaging\InfobipWhatsappService;
use Illuminate\Support\Facades\Queue;

test('BeemSmsService normalizes and masks phone numbers accurately', function () {
    $service = new BeemSmsService;

    // Normalization
    expect($service->normalizePhone('0712345678'))->toBe('255712345678');
    expect($service->normalizePhone('+255712345678'))->toBe('255712345678');
    expect($service->normalizePhone('255712345678'))->toBe('255712345678');

    // Masking
    expect(BeemSmsService::maskPhone('255712345678'))->toBe('+25571 *** 678');
});

test('BeemSmsService sends simulated SMS in testing environment', function () {
    $service = new BeemSmsService;
    $result = $service->sendSms('0712345678', 'Test message');

    expect($result['isSuccessful'])->toBeTrue();
    expect($result['recipient'])->toBe('255712345678');
});

test('InfobipWhatsappService sends simulated template and text messages', function () {
    $service = new InfobipWhatsappService;

    $templateResult = $service->sendTemplateMessage('0712345678', 'invitation_tamplate_en', ['John', 'Wedding']);
    expect($templateResult['successful'])->toBeTrue();

    $textResult = $service->sendTextMessage('0712345678', 'Direct WhatsApp text');
    expect($textResult['successful'])->toBeTrue();
});

test('BrevoEmailService sends simulated transactional emails', function () {
    $service = new BrevoEmailService;

    $result = $service->sendTransactionalEmail('john@example.com', 'John Doe', 'Test Subject', '<p>Hello</p>');
    expect($result['successful'])->toBeTrue();
});

test('background jobs can be dispatched to dedicated queues', function () {
    Queue::fake();

    SendSmsJob::dispatch('0712345678', 'Queue SMS test');
    SendWhatsappJob::dispatch('0712345678', 'invitation_template', ['Param1']);
    SendEmailJob::dispatch('john@example.com', 'John', 'Subject', '<p>Body</p>');
    GenerateInvitationCardJob::dispatch(1, 1);
    ImportInviteesJob::dispatch(1, '/tmp/test.xlsx');

    Queue::assertPushedOn('messages', SendSmsJob::class);
    Queue::assertPushedOn('messages', SendWhatsappJob::class);
    Queue::assertPushedOn('emails', SendEmailJob::class);
    Queue::assertPushedOn('cards', GenerateInvitationCardJob::class);
    Queue::assertPushedOn('imports', ImportInviteesJob::class);
});
