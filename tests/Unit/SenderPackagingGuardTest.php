<?php

declare(strict_types=1);

namespace VGBridgeTests\Unit;

use VGBridgeTests\Support\TestCase;

final class SenderPackagingGuardTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 2);
    }

    public function test_generated_zips_do_not_contain_forbidden_paths(): void
    {
        $buildScript = $this->rootDir . '/tools/build-zips.sh';
        $checkScript = $this->rootDir . '/tools/check-zips.sh';

        exec('bash ' . escapeshellarg($buildScript) . ' 2>&1', $buildOutput, $buildCode);
        $this->assertSame(0, $buildCode, implode("\n", $buildOutput));

        $senderZip = $this->rootDir . '/releases/voelgoed-course-bridge-sender-v1.0.0.zip';
        $receiverZip = $this->rootDir . '/releases/voelgoed-course-bridge-receiver-v1.0.0.zip';

        $this->assertFileExists($senderZip);
        $this->assertFileExists($receiverZip);

        $forbidden = ['.git/', 'docs/', 'tests/', 'releases/', 'wp-config.php', '.env', 'vendor/', 'node_modules/'];

        foreach ([$senderZip, $receiverZip] as $zipFile) {
            exec('unzip -Z1 ' . escapeshellarg($zipFile), $entries, $exitCode);
            $this->assertSame(0, $exitCode, "Could not list entries in {$zipFile}");
            $this->assertNotEmpty($entries);

            foreach ($entries as $entry) {
                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $entry,
                        "Forbidden path found in {$zipFile} entry: {$entry}"
                    );
                }
            }
        }

        exec('bash ' . escapeshellarg($checkScript) . ' 2>&1', $checkOutput, $checkCode);
        $this->assertSame(0, $checkCode, implode("\n", $checkOutput));
    }
}
