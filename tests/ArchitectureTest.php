<?php

arch()->preset()->php();
arch()->preset()->security();

/*
|--------------------------------------------------------------------------
| Bounded-context boundaries
|--------------------------------------------------------------------------
|
| Allowed edges, by omission: every context may use App\Support and the
| Identity models (policies and authorship need User); Reading is the read
| side and may query Publishing and Taxonomy; Publishing may use Taxonomy
| and Identity (models and events); Taxonomy may touch Publishing models
| only; App\Http may use everything. Cross-context reactions travel through
| domain events, never direct calls.
*/

arch('the domain layer does not depend on the http interface')
    ->expect('App\Domain')
    ->not->toUse('App\Http');

arch('the support kernel is dependency-free')
    ->expect('App\Support')
    ->not->toUse(['App\Domain', 'App\Http', 'App\Providers']);

arch('identity is a leaf context')
    ->expect('App\Domain\Identity')
    ->not->toUse(['App\Domain\Publishing', 'App\Domain\Taxonomy', 'App\Domain\Reading', 'App\Domain\Inbox', 'App\Domain\Community', 'App\Domain\Analytics']);

arch('inbox depends on identity models at most')
    ->expect('App\Domain\Inbox')
    ->not->toUse(['App\Domain\Publishing', 'App\Domain\Taxonomy', 'App\Domain\Reading', 'App\Domain\Community', 'App\Domain\Analytics']);

arch('publishing never reaches into reading or inbox')
    ->expect('App\Domain\Publishing')
    ->not->toUse(['App\Domain\Reading', 'App\Domain\Inbox', 'App\Domain\Community', 'App\Domain\Analytics']);

arch('taxonomy touches publishing only through its models')
    ->expect('App\Domain\Taxonomy')
    ->not->toUse([
        'App\Domain\Reading',
        'App\Domain\Inbox',
        'App\Domain\Community',
        'App\Domain\Analytics',
        'App\Domain\Publishing\Actions',
        'App\Domain\Publishing\Casts',
        'App\Domain\Publishing\Concerns',
        'App\Domain\Publishing\Console',
        'App\Domain\Publishing\Enums',
        'App\Domain\Publishing\Events',
        'App\Domain\Publishing\Exceptions',
        'App\Domain\Publishing\Listeners',
        'App\Domain\Publishing\Policies',
        'App\Domain\Publishing\Providers',
        'App\Domain\Publishing\Queries',
        'App\Domain\Publishing\Rules',
        'App\Domain\Publishing\ValueObjects',
    ]);

arch('reading never depends on inbox')
    ->expect('App\Domain\Reading')
    ->not->toUse(['App\Domain\Inbox', 'App\Domain\Community', 'App\Domain\Analytics']);

arch('community depends only on publishing and identity contexts')
    ->expect('App\Domain\Community')
    ->not->toUse(['App\Domain\Analytics', 'App\Domain\Reading', 'App\Domain\Taxonomy', 'App\Domain\Inbox']);

arch('analytics does not call actions from other contexts')
    ->expect('App\Domain\Analytics')
    ->not->toUse([
        'App\Domain\Publishing\Actions',
        'App\Domain\Taxonomy\Actions',
        'App\Domain\Identity\Actions',
        'App\Domain\Community\Actions',
        'App\Domain\Reading\Actions',
    ]);

arch('analytics observes community only through immutable events')
    ->expect('App\Domain\Analytics')
    ->not->toUse([
        'App\Domain\Community\Actions',
        'App\Domain\Community\Console',
        'App\Domain\Community\Enums',
        'App\Domain\Community\Mail',
        'App\Domain\Community\Models',
        'App\Domain\Community\Policies',
        'App\Domain\Community\Providers',
        'App\Domain\Community\Support',
        'App\Domain\Community\ValueObjects',
    ]);

arch('assistant reads publishing and taxonomy but never their write paths')
    ->expect('App\Domain\Assistant')
    ->not->toUse([
        'App\Domain\Publishing\Actions',
        'App\Domain\Publishing\Console',
        'App\Domain\Publishing\Listeners',
        'App\Domain\Publishing\Policies',
        'App\Domain\Publishing\Providers',
        'App\Domain\Taxonomy\Actions',
        'App\Domain\Reading',
        'App\Domain\Inbox',
        'App\Domain\Community',
        'App\Domain\Analytics',
    ]);

arch('no context depends on the assistant')
    ->expect([
        'App\Domain\Publishing',
        'App\Domain\Taxonomy',
        'App\Domain\Reading',
        'App\Domain\Inbox',
        'App\Domain\Community',
        'App\Domain\Analytics',
        'App\Domain\Identity',
    ])
    ->not->toUse('App\Domain\Assistant');

arch('domain events are immutable')
    ->expect('App\Domain\Publishing\Events')
    ->toBeFinal()->toBeReadonly()
    ->and('App\Domain\Taxonomy\Events')->toBeFinal()->toBeReadonly()
    ->and('App\Domain\Identity\Events')->toBeFinal()->toBeReadonly()
    ->and('App\Domain\Inbox\Events')->toBeFinal()->toBeReadonly()
    ->and('App\Domain\Community\Events')->toBeFinal()->toBeReadonly();
