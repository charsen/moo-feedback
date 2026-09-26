<?php declare(strict_types=1);

use Illuminate\Contracts\Container\BindingResolutionException;
use Mooeen\Contract\PersonnelNameResolver;

it('requires explicit shared name wiring without moo-system', function () {
    expect(app()->bound(PersonnelNameResolver::class))->toBeFalse();
    expect(fn () => app(PersonnelNameResolver::class))->toThrow(BindingResolutionException::class);
});

it('preserves the explicit shared name implementation when registering the package', function () {
    $names = Mockery::mock(PersonnelNameResolver::class);
    $names->shouldReceive('resolveNames')->once()->with(['17', '18'])->andReturn(['17' => 'Former Person']);
    app()->instance(PersonnelNameResolver::class, $names);
    (new \Mooeen\Feedback\MooeenFeedbackServiceProvider(app()))->register();
    expect(app(PersonnelNameResolver::class))->toBe($names)
        ->and(app(PersonnelNameResolver::class)->resolveNames(['17', '18']))->toBe(['17' => 'Former Person']);
});
