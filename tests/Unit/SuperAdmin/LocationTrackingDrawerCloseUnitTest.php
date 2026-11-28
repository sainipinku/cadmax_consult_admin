<?php

namespace Tests\Unit\SuperAdmin;

use Tests\TestCase;

class LocationTrackingDrawerCloseUnitTest extends TestCase
{
    public function test_location_tracking_state_helper_module_exists_and_handles_close_and_bounds_cleanly(): void
    {
        $helperPath = resource_path('js/lib/locationTrackingState.js');
        $this->assertFileExists($helperPath, 'resources/js/lib/locationTrackingState.js must exist');

        $nodeScript = <<<'JS'
import assert from 'node:assert/strict';
import {
    createClosedHistoryPatch,
    isLatestHistoryRequest,
    fitMapToCoordinates,
} from './resources/js/lib/locationTrackingState.js';

const closed = createClosedHistoryPatch();
assert.equal(closed.selectedEmployee, null);
assert.equal(closed.activeProfile, null);
assert.deepEqual(closed.historyPoints, []);
assert.deepEqual(closed.routePath, []);
assert.equal(closed.routeMetrics, null);
assert.equal(closed.selectedCheckpoint, null);
assert.equal(closed.isPlaying, false);
assert.equal(closed.playbackIndex, 0);
assert.equal(closed.historyLoading, false);
assert.equal(closed.historyError, '');

assert.equal(isLatestHistoryRequest(3, 3), true);
assert.equal(isLatestHistoryRequest(4, 3), false);

// Single coordinate pans & zooms cleanly
let pannedTo = null;
let zoomedTo = null;
let fitted = false;
const mockMap = {
    panTo(coords) { pannedTo = coords; },
    setZoom(zoom) { zoomedTo = zoom; },
    fitBounds() { fitted = true; },
};

fitMapToCoordinates(mockMap, [{ latitude: 26.9124, longitude: 75.7873 }], {});
assert.deepEqual(pannedTo, { lat: 26.9124, lng: 75.7873 });
assert.equal(zoomedTo, 16);
assert.equal(fitted, false);

// Multi-coordinate does not throw when google.maps.LatLngBounds is unavailable
fitMapToCoordinates(mockMap, [
    { lat: 26.9124, lng: 75.7873 },
    { lat: 26.9200, lng: 75.7900 },
], null);
assert.equal(fitted, false);

// Multi-coordinate calls fitBounds when LatLngBounds is available
const extendedPoints = [];
class FakeBounds {
    extend(pt) { extendedPoints.push(pt); }
}
fitMapToCoordinates(mockMap, [
    { lat: 26.9124, lng: 75.7873 },
    { latitude: 26.9200, longitude: 75.7900 },
], { LatLngBounds: FakeBounds });
assert.equal(extendedPoints.length, 2);
assert.equal(fitted, true);

console.log('ALL_NODE_UNIT_ASSERTIONS_PASSED');
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
        $this->assertStringContainsString('ALL_NODE_UNIT_ASSERTIONS_PASSED', $stdout);
    }

    public function test_history_drawer_inner_cross_button_is_not_blocked_by_sibling_stacking_context(): void
    {
        $jsxPath = resource_path('js/Pages/SuperAdmin/LocationTracking.jsx');
        $this->assertFileExists($jsxPath);

        $content = file_get_contents($jsxPath);
        $this->assertIsString($content);

        // Must import shared close state & bounds helper
        $this->assertStringContainsString('@/lib/locationTrackingState', $content);
        $this->assertStringContainsString('createClosedHistoryPatch', $content);
        $this->assertStringContainsString('isLatestHistoryRequest', $content);
        $this->assertStringContainsString('historyRequestRef', $content);

        // Extract the Snapshot Card header block containing the inner close button
        $headerMatched = preg_match(
            '/\{\/\*\s*Header:\s*Employee Snapshot Card\s*\*\/\}(.*?)\{\/\*\s*DAY FILTERS/s',
            $content,
            $headerMatches
        );
        $this->assertSame(1, $headerMatched, 'Employee Snapshot Card header markup not found');
        $headerBlock = $headerMatches[1];

        // Previously the flex sibling had "relative" right after an un-z-indexed absolute button, covering its hitbox
        $this->assertStringNotContainsString(
            'flex items-center justify-between gap-3 relative pr-8',
            $headerBlock,
            'Sibling flex row must not use conflicting relative positioning over the close button'
        );

        // Inner close button must declare higher z-index, pointer cursor, and accessible aria-label
        $buttonMatched = preg_match(
            '/<button\b(?:(?!<\/button>).)*?title="Close & back to live map".*?>\s*<FaTimes/s',
            $headerBlock,
            $buttonMatches
        );
        $this->assertSame(1, $buttonMatched, 'Inner close button in drawer header not found');
        $buttonTag = $buttonMatches[0];

        $this->assertMatchesRegularExpression('/\bz-(10|20|30)\b/', $buttonTag, 'Inner close button must declare an explicit z-index');
        $this->assertStringContainsString('cursor-pointer', $buttonTag);
        $this->assertStringContainsString('aria-label="Close employee history"', $buttonTag);
        $this->assertStringContainsString('onClick={handleCloseHistory}', $buttonTag);
    }

    public function test_snap_map_profile_dismiss_cross_button_declares_explicit_stacking_order(): void
    {
        $jsxPath = resource_path('js/Pages/SuperAdmin/LocationTracking.jsx');
        $content = file_get_contents($jsxPath);
        $this->assertIsString($content);

        $buttonMatched = preg_match(
            '/<button\b(?:(?!<\/button>).)*?title="Dismiss".*?>\s*<FaTimes/s',
            $content,
            $buttonMatches
        );
        $this->assertSame(1, $buttonMatched, 'Snap Map Profile dismiss button not found');
        $buttonTag = $buttonMatches[0];

        $this->assertMatchesRegularExpression('/\bz-(10|20|30)\b/', $buttonTag, 'Profile dismiss button must declare explicit z-index');
        $this->assertStringContainsString('cursor-pointer', $buttonTag);
        $this->assertStringContainsString('aria-label="Dismiss employee profile"', $buttonTag);
    }
}
