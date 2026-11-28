<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ReadyForSubmissionLabelUnitTest extends TestCase
{
    public function test_construction_status_helper_maps_ready_for_construction_to_ready_for_submission(): void
    {
        $helperPath = resource_path('js/lib/constructionStatus.js');
        $this->assertFileExists($helperPath, 'resources/js/lib/constructionStatus.js must exist');

        $nodeScript = <<<'JS'
import assert from 'node:assert/strict';
import {
    READY_FOR_SUBMISSION_LABEL,
    STATUS_DISPLAY_LABELS,
    resolveStatusDisplayLabel,
} from './resources/js/lib/constructionStatus.js';

assert.equal(READY_FOR_SUBMISSION_LABEL, 'Ready for submission');
assert.equal(STATUS_DISPLAY_LABELS.ready_for_construction, 'Ready for submission');

assert.deepEqual(resolveStatusDisplayLabel('ready_for_construction'), {
    label: 'Ready for submission',
    hasCustomLabel: true,
});
assert.deepEqual(resolveStatusDisplayLabel('Ready for Construction'), {
    label: 'Ready for submission',
    hasCustomLabel: true,
});
assert.deepEqual(resolveStatusDisplayLabel('budget_pending'), {
    label: 'budget pending',
    hasCustomLabel: false,
});
assert.deepEqual(resolveStatusDisplayLabel('ready_for_construction', 'Manual Override'), {
    label: 'Manual Override',
    hasCustomLabel: true,
});

console.log('STATUS_HELPER_ASSERTIONS_PASSED');
JS;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open('node --input-type=module', $descriptors, $pipes, base_path());
        $this->assertIsResource($process);

        fwrite($pipes[0], $nodeScript);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, "Node helper test failed: {$stderr}\n{$stdout}");
        $this->assertStringContainsString('STATUS_HELPER_ASSERTIONS_PASSED', $stdout);
    }

    public function test_status_badge_and_ui_pages_use_ready_for_submission_instead_of_ready_for_construction(): void
    {
        $statusBadge = file_get_contents(resource_path('js/Pages/Construction/Components/StatusBadge.jsx'));
        $this->assertIsString($statusBadge);
        $this->assertStringContainsString('@/lib/constructionStatus', $statusBadge);
        $this->assertStringContainsString('resolveStatusDisplayLabel', $statusBadge);

        $uiFiles = [
            'resources/js/Pages/Admin/Construction/Dashboard.jsx',
            'resources/js/Pages/Member/Construction/Projects/Index.jsx',
            'resources/js/Pages/SuperAdmin/Construction/Drafting/Index.jsx',
            'resources/js/Pages/SuperAdmin/Construction/Projects/Show.jsx',
        ];

        foreach ($uiFiles as $relativePath) {
            $content = file_get_contents(base_path($relativePath));
            $this->assertIsString($content);
            $this->assertDoesNotMatchRegularExpression(
                '/Ready [Ff]or Construction/',
                $content,
                "{$relativePath} must not display legacy 'Ready For/for Construction' wording"
            );
            $this->assertStringContainsString('READY_FOR_SUBMISSION_LABEL', $content);
        }
    }
}
