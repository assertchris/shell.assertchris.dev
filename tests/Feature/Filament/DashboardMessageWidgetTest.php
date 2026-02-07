<?php

use App\Filament\Widgets\RecentDashboardMessagesWidget;
use App\Models\DashboardMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('widget renders successfully', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(RecentDashboardMessagesWidget::class)
        ->assertSuccessful();
});

test('widget shows unread unarchived messages', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->create(['title' => 'Unread Message']);

    $this->actingAs($user);

    Livewire::test(RecentDashboardMessagesWidget::class)
        ->assertSee('Unread Message');
});

test('widget does not show read messages', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->read()->create(['title' => 'Read Message']);

    $this->actingAs($user);

    Livewire::test(RecentDashboardMessagesWidget::class)
        ->assertDontSee('Read Message');
});

test('widget does not show archived messages', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->archived()->create(['title' => 'Archived Message']);

    $this->actingAs($user);

    Livewire::test(RecentDashboardMessagesWidget::class)
        ->assertDontSee('Archived Message');
});

test('widget does not show other users messages', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DashboardMessage::factory()->for($other)->create(['title' => 'Other User Message']);

    $this->actingAs($user);

    Livewire::test(RecentDashboardMessagesWidget::class)
        ->assertDontSee('Other User Message');
});

test('widget shows at most 5 messages', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->count(7)->create();

    $this->actingAs($user);

    $component = Livewire::test(RecentDashboardMessagesWidget::class);

    $records = $component->instance()->getTableRecords();

    expect($records)->toHaveCount(5);
});
