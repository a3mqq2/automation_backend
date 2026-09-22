<?php

namespace App\Services\Pages;

final readonly class AvailablePage
{
    public function __construct(
        public FacebookAccountPage $page,
        public ?int $facebookPageId,
        public bool $isConnected,
        public bool $connectedByAnotherAccount,
    ) {
    }
}
