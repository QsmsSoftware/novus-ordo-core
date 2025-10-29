<?php
namespace App\Domain;

readonly class DeploymentCommand {
    public function __construct(
        public int $territoryId,
        public DivisionType $divisionType,
    ) {}
}