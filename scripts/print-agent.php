#!/usr/bin/env php
<?php

/**
 * Compatibilidade: encaminha para o pacote standalone em /print-agent.
 *
 * Prefira usar:
 *   print-agent/iniciar.bat
 *   ou php print-agent/print-agent.php
 */

declare(strict_types=1);

require dirname(__DIR__).DIRECTORY_SEPARATOR.'print-agent'.DIRECTORY_SEPARATOR.'print-agent.php';
