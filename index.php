<?php

// Database configuration
$is_prod = getenv('IS_PROD') === 'y';
$db_path = $is_prod ? '/data/database.sqlite' : './database.sqlite';

// Error reporting
if (!$is_prod) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Ensure the data directory exists in production
if ($is_prod && !is_dir('/data')) {
    mkdir('/data', 0777, true);
}

// Initialize database connection
try {
    $db = new SQLite3($db_path);
    $db->enableExceptions(true);

    // Enable Write-Ahead Logging for better concurrency and performance
    $db->exec('PRAGMA journal_mode = WAL');
    // Increase cache size to approximately 100MB (-25000 pages, where each page is 4KB)
    $db->exec('PRAGMA cache_size = -25000');
    // Set synchronous mode to NORMAL for a balance between safety and performance
    $db->exec('PRAGMA synchronous = NORMAL');
    // Store temporary tables and indices in memory instead of on disk
    $db->exec('PRAGMA temp_store = MEMORY');
    // Set the maximum size of the memory-mapped I/O to approximately 1GB
    $db->exec('PRAGMA mmap_size = 1000000000');
    // Enable foreign key constraints for data integrity
    $db->exec('PRAGMA foreign_keys = ON');
    // Set a busy timeout of 5 seconds to wait if the database is locked
    $db->exec('PRAGMA busy_timeout = 5000');
    // Enable incremental vacuuming to reclaim unused space and keep the database file size optimized
    $db->exec('PRAGMA auto_vacuum = INCREMENTAL');

    // Create table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author TEXT NOT NULL,
        content TEXT NOT NULL
    )');
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage() .
        ' (DB Path: ' . $db_path .
        ', Is Prod: ' . ($is_prod ? 'Yes' : 'No') .
        ', Directory Writable: ' . (is_writable(dirname($db_path)) ? 'Yes' : 'No') .
        ', PHP User: ' . get_current_user() .
        ')');
}

function getVpsCapacity()
{
    $capacity = [
        'vCPUs' => 2, // Default value
        'CPU model' => 'Unknown',
        'Platform' => php_uname('s') . ', ' . php_uname('m') . ', ' . php_uname('r'),
        'Total RAM' => 'Unknown',
        'CPU usage' => 'Unknown',
        'Memory usage' => 'Unknown',
    ];

    // Get CPU model and count
    if (PHP_OS_FAMILY === 'Darwin') { // macOS
        $sysctl_output = shell_exec('sysctl -n machdep.cpu.brand_string hw.physicalcpu');
        if ($sysctl_output !== null) {
            $sysctl_lines = explode("\n", $sysctl_output);
            $capacity['CPU model'] = $sysctl_lines[0] ?? 'Unknown';
            $capacity['vCPUs'] = intval($sysctl_lines[1] ?? 2);
        }
    } elseif (is_readable('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        if (preg_match('/model name\s+:\s+(.+)$/m', $cpuinfo, $matches)) {
            $capacity['CPU model'] = $matches[1];
        }
        $cpu_count = substr_count($cpuinfo, 'processor');
        if ($cpu_count > 0) {
            $capacity['vCPUs'] = $cpu_count;
        }
    }

    // Get total RAM
    if (PHP_OS_FAMILY === 'Darwin') { // macOS
        $mem = shell_exec('sysctl hw.memsize');
        if (preg_match('/hw.memsize: (\d+)/', $mem, $matches)) {
            $total_ram = $matches[1];
            $capacity['Total RAM'] = round($total_ram / 1024 / 1024 / 1024, 1) . 'GB';
        }
    } elseif (is_readable('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal:\s+(\d+)\s+kB/i', $meminfo, $matches)) {
            $total_ram = $matches[1] * 1024; // Convert kB to bytes
            $capacity['Total RAM'] = round($total_ram / 1024 / 1024 / 1024, 1) . 'GB';
        }
    }

    // Get CPU usage
    if (PHP_OS_FAMILY === 'Darwin') { // macOS
        $top_output = shell_exec('top -l 1 -n 0 | grep "CPU usage"');
        if (preg_match('/(\d+\.\d+)% idle/', $top_output, $matches)) {
            $idle_percentage = floatval($matches[1]);
            $capacity['CPU usage'] = round(100 - $idle_percentage, 1) . '%';
        }
    } elseif (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $capacity['CPU usage'] = round(($load[0] / $capacity['vCPUs']) * 100, 1) . '%';
    }

    // Get memory usage
    if (function_exists('memory_get_usage')) {
        $used_memory = memory_get_usage(true);
        if ($capacity['Total RAM'] !== 'Unknown') {
            $total_memory = floatval($capacity['Total RAM']) * 1024 * 1024 * 1024; // Convert GB to bytes
            $capacity['Memory usage'] = round(($used_memory / $total_memory) * 100, 1) . '%';
        }
    }

    return $capacity;
}

// Add this function for caching
function getCachedDbTest($db)
{
    $cacheFile = sys_get_temp_dir() . '/db_test_cache.json';
    $cacheExpiry = getenv('IS_PROD') === 'y' ? 300 : 0; // Cache expiry in seconds

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheExpiry)) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $result = runDbTest($db);
    file_put_contents($cacheFile, json_encode($result));
    return $result;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Start output buffering
    ob_start();

    try {
        switch ($_GET['action']) {
            case 'dbTest':
                $result = getCachedDbTest($db);
                echo json_encode($result);
                break;
            case 'getCapacity':
                $result = getVpsCapacity();
                echo json_encode($result);
                break;
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }

    // Get the contents of the output buffer
    $output = ob_get_clean();

    // Check if the output is valid JSON
    if (json_decode($output) === null) {
        // If it's not valid JSON, there was probably a PHP error
        echo json_encode(['error' => 'PHP Error: ' . $output]);
    } else {
        // If it's valid JSON, send it as is
        echo $output;
    }
    exit;
}

function runDbTest($db)
{
    $start = microtime(true);
    $totalInserts = 350000;
    $chunkSize = 100;
    $writes = 0;
    $failures = 0;
    $newRecords = [];

    $insertStmt = $db->prepare('INSERT INTO comments (author, content) VALUES (:author, :content)');
    $selectStmt = $db->prepare('SELECT * FROM comments WHERE id = :id');

    for ($i = 0; $i < $totalInserts; $i += $chunkSize) {
        $db->exec('BEGIN TRANSACTION');
        for ($j = 0; $j < $chunkSize && ($i + $j) < $totalInserts; $j++) {
            $author = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 7);
            $content = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 7);
            $insertStmt->bindValue(':author', $author, SQLITE3_TEXT);
            $insertStmt->bindValue(':content', $content, SQLITE3_TEXT);
            if ($insertStmt->execute()) {
                $newRecords[] = $db->lastInsertRowID();
                $writes++;
            } else {
                $failures++;
            }
        }
        $db->exec('COMMIT');
    }

    $writeTime = microtime(true) - $start;
    $writesPerSecond = round($writes / $writeTime);

    // Measure reads
    $readStart = microtime(true);
    $reads = 0;
    $readSampleSize = min(10000, count($newRecords));
    $readSample = array_rand(array_flip($newRecords), $readSampleSize);
    foreach ($readSample as $id) {
        $selectStmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $selectStmt->execute();
        $reads++;
    }
    $readTime = microtime(true) - $readStart;
    $readsPerSecond = round(($reads / $readTime) * (count($newRecords) / $readSampleSize));

    // Get total count and DB size
    $total = $db->querySingle('SELECT COUNT(*) FROM comments');
    $dbSizeInMb = round(filesize($GLOBALS['db_path']) / 1024 / 1024);

    return [
        'dbSizeInMb' => $dbSizeInMb,
        'failureRate' => round(($failures / $writes) * 100, 2),
        'reads' => $reads,
        'readsPerSecond' => $readsPerSecond,
        'total' => $total,
        'writes' => $writes,
        'writesPerSecond' => $writesPerSecond,
        'writeTime' => $writeTime,
    ];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>PHP vs NextJS - Ashley Rudland's PHP Playground</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<style>
		.custom-gradient {
			background: linear-gradient(90deg, #4F46E5, #7C3AED);
		}
	</style>
</head>

<body class="bg-white">

	<main class="flex min-h-screen flex-col p-10 space-y-6">
		<h1 class="font-bold text-xl">
			PHP vs NextJS
		</h1>

		<p>
			Built by <a href="https://x.com/ashleyrudland" class="text-blue-600 underline underline-offset-4">@ashleyrudland</a>. Inspired by recent <a class="text-blue-500 underline underline-offset-4" href="https://x.com/levelsio">@levelsio</a> lex <a href="https://www.youtube.com/watch?v=oFtjKbXKqbg" class="text-blue-600 underline underline-offset-4" target="_blank">pod</a>. I was curious to see how easy/hard it is to build a
			PHP app using jQuery vs standard NextJS approach most engineers use today. Also how performant is it?
		</p>

		<h2 class="font-semibold">The result:</h2>
		<p>
		Easy & simple. 1 index.php file vs 5+ files in TypeScript for NextJS. View the source code
			<a href="https://github.com/ashleyrudland/php-jquery-sqlite-starter-pack" class="text-blue-600 underline underline-offset-4" target="_blank"
		>
				here
			</a></p>

		<h2 class="font-semibold">The performance?</h2>

		<p>PHP is around 5x faster. NextJS can do 30,000 writes/second on a $5/mo VPS, PHP can do 150,000+ writes/second on the same VPS! Try NextJS app <a href="https://vps.ashleyrudland.com" class="text-blue-600 underline underline-offset-4" target="_blank">here</a> and see below the results.</p>


		<div class="flex flex-col gap-6 sm:flex-row sm:gap-10">
			<div id="dbTest" class="bg-white p-6 rounded-lg shadow-md flex-1">
				<h2 class="text-lg font-semibold mb-4">SQLite Writes/sec</h2>
				<div id="dbTestContent">
					<div class="flex flex-row gap-1 items-center">
						<svg class="animate-spin h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
							viewBox="0 0 24 24">
							<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
							</circle>
							<path class="opacity-75" fill="currentColor"
								d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
							</path>
						</svg>
						<span>Running test (<span id="runningTime">0.0</span>s)...</span>
					</div>
				</div>
			</div>
			<div id="capacity" class="bg-white p-6 rounded-lg shadow-md flex-1">
            <h2 class="text-lg font-semibold mb-4">VPS Capacity</h2>
            <div id="capacityContent">
                <div class="flex flex-row gap-1 items-center">
                    <svg class="animate-spin h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Loading capacity data...</span>
                </div>
            </div>
        </div>
		</div>
	</main>

	<script>
		$(document).ready(function () {
			let startTime;
			let timer;

			function updateRunningTime() {
				if (startTime) {
					let runningTime = (Date.now() - startTime) / 1000;
					$('#runningTime').text(runningTime.toFixed(1));
				}
			}

			function getCapacity() {
				$.ajax({
					url: '?action=getCapacity',
					method: 'GET',
					dataType: 'json',
					success: function(result) {
						let content = '<ul>';
						for (let [key, value] of Object.entries(result)) {
							content += `<li>${key}: ${value}</li>`;
						}
						content += '</ul>';
						$('#capacityContent').html(content);
					},
					error: function(xhr, status, error) {
						$('#capacityContent').html(`<p>Error: ${error}</p>`);
					}
				});
			}

			function runDbTest() {
				startTime = Date.now();
				timer = setInterval(updateRunningTime, 200);

				$.ajax({
					url: '?action=dbTest',
					method: 'GET',
					dataType: 'json',
					success: function (result) {
						clearInterval(timer);
						let content = '<ul>';
						content += `<li>DB size: ${result.dbSizeInMb >= 1024 ? (result.dbSizeInMb / 1024).toLocaleString(undefined, { maximumFractionDigits: 1 }) + 'GB' : result.dbSizeInMb.toLocaleString() + 'MB'}</li>`;
						content += `<li>Table size: ${result.total.toLocaleString()} records</li>`;
						content += `<li>Reads/sec: ${result.readsPerSecond.toLocaleString()}</li>`;
						content += `<li class="font-medium">Writes/sec: ${result.writesPerSecond.toLocaleString()}</li>`;
						if (result.failureRate > 0) {
							content += `<li>Failure rate: ${result.failureRate}%</li>`;
						}
						content += '</ul>';
						$('#dbTestContent').html(content);
					},
					error: function (xhr, status, error) {
						clearInterval(timer);
						$('#dbTestContent').html(`<p>Error: ${error}</p>`);
					}
				});
			}

			runDbTest();
			getCapacity();
		});
	</script>
</body>

</html>
