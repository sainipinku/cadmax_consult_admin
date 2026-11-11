<?php

namespace Tests\Unit;

use Tests\TestCase;

class MemberTaskDeduplicationUnitTest extends TestCase
{
    public function test_deduplication_prioritizes_unified_task_over_legacy_mirror(): void
    {
        $unifiedRows = [
            [
                'id' => 4,
                'task_code' => null,
                'project_id' => 10,
                'title' => 'for today tast title 10-11-26',
                'checklist_total' => 2,
                'source' => 'unified',
            ],
        ];

        $legacyRows = [
            [
                'id' => 24,
                'task_code' => 'TSK-00004',
                'project_id' => 10,
                'title' => 'for today tast title 10-11-26',
                'checklist_total' => 0,
                'source' => 'legacy',
            ],
        ];

        // Deduplication algorithm prioritizing unified
        $seenCodes = [];
        $seenTitleProject = [];
        $merged = collect();

        // 1. Process UNIFIED first
        foreach ($unifiedRows as $t) {
            $rawCode = !empty($t['task_code']) ? strtolower(trim((string) $t['task_code'])) : null;
            $computedCode = strtolower('tsk-' . str_pad((string) $t['id'], 5, '0', STR_PAD_LEFT));
            if ($rawCode) {
                $seenCodes[$rawCode] = true;
            }
            $seenCodes[$computedCode] = true;

            $key = ($t['project_id'] ?? 0) . ':' . trim(strtolower((string) ($t['title'] ?? '')));
            $seenTitleProject[$key] = true;

            $merged->push($t);
        }

        // 2. Process LEGACY second
        foreach ($legacyRows as $t) {
            $rawCode = !empty($t['task_code']) ? strtolower(trim((string) $t['task_code'])) : null;
            $computedCode = strtolower('tsk-' . str_pad((string) $t['id'], 5, '0', STR_PAD_LEFT));
            $isSeenCode = ($rawCode && isset($seenCodes[$rawCode])) || isset($seenCodes[$computedCode]);

            $key = ($t['project_id'] ?? 0) . ':' . trim(strtolower((string) ($t['title'] ?? '')));
            $isSeenTitleProject = isset($seenTitleProject[$key]);

            if ($isSeenCode || $isSeenTitleProject) {
                continue;
            }

            $merged->push($t);
        }

        // Must only contain 1 task
        $this->assertCount(1, $merged);

        // The kept task must be unified id 4 with 2 checklists
        $kept = $merged->first();
        $this->assertSame(4, $kept['id']);
        $this->assertSame('unified', $kept['source']);
        $this->assertSame(2, $kept['checklist_total']);
    }

    public function test_legacy_only_task_is_preserved_when_no_unified_equivalent(): void
    {
        $unifiedRows = [
            [
                'id' => 4,
                'task_code' => 'TSK-00004',
                'project_id' => 10,
                'title' => 'Unified Task',
                'source' => 'unified',
            ],
        ];

        $legacyRows = [
            [
                'id' => 99,
                'task_code' => 'LEGACY-099',
                'project_id' => 10,
                'title' => 'Independent Legacy Task',
                'source' => 'legacy',
            ],
        ];

        $seenCodes = [];
        $seenTitleProject = [];
        $merged = collect();

        foreach ($unifiedRows as $t) {
            $rawCode = !empty($t['task_code']) ? strtolower(trim((string) $t['task_code'])) : null;
            $computedCode = strtolower('tsk-' . str_pad((string) $t['id'], 5, '0', STR_PAD_LEFT));
            if ($rawCode) {
                $seenCodes[$rawCode] = true;
            }
            $seenCodes[$computedCode] = true;

            $key = ($t['project_id'] ?? 0) . ':' . trim(strtolower((string) ($t['title'] ?? '')));
            $seenTitleProject[$key] = true;

            $merged->push($t);
        }

        foreach ($legacyRows as $t) {
            $rawCode = !empty($t['task_code']) ? strtolower(trim((string) $t['task_code'])) : null;
            $computedCode = strtolower('tsk-' . str_pad((string) $t['id'], 5, '0', STR_PAD_LEFT));
            $isSeenCode = ($rawCode && isset($seenCodes[$rawCode])) || isset($seenCodes[$computedCode]);

            $key = ($t['project_id'] ?? 0) . ':' . trim(strtolower((string) ($t['title'] ?? '')));
            $isSeenTitleProject = isset($seenTitleProject[$key]);

            if ($isSeenCode || $isSeenTitleProject) {
                continue;
            }

            $merged->push($t);
        }

        $this->assertCount(2, $merged);
        $this->assertEquals([4, 99], $merged->pluck('id')->all());
    }
}

