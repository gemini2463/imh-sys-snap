<?php
// index.php
// This file is part of the IMH sys-snap plugin for cPanel/WHM, and CWP.
// It provides a web interface to view system snapshots, manage sys-snap, and display 24-hour statistics.
// Path on cPanel: /usr/local/cpanel/whostmgr/docroot/cgi/imh-sys-snap/index.php
// Path on CWP: /usr/local/cwpsrv/htdocs/resources/admin/modules/imh-sys-snap/index.php





$isCPanelServer = (
    (is_dir('/usr/local/cpanel') || is_dir('/var/cpanel') || is_dir('/etc/cpanel')) && (is_file('/usr/local/cpanel/cpanel') || is_file('/usr/local/cpanel/version'))
);

if ($isCPanelServer) {
    if (getenv('REMOTE_USER') !== 'root') exit('Access Denied');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} else { // CWP
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1 || !isset($_SESSION['username']) || $_SESSION['username'] !== 'root') { exit('Access Denied'); }
};










// Secure forms

$CSRF_TOKEN = NULL;

if (!isset($_SESSION['csrf_token'])) {
    $CSRF_TOKEN = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $CSRF_TOKEN;
} else {
    $CSRF_TOKEN = $_SESSION['csrf_token'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['csrf_token'])) {
    die("CSRF token missing");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token");
}







// Defaults and validation

$start_hour = isset($_POST['start_hour']) ? (int)$_POST['start_hour'] : 0;
$start_min  = isset($_POST['start_min'])  ? (int)$_POST['start_min']  : 0;
$end_hour   = isset($_POST['end_hour'])   ? (int)$_POST['end_hour']   : 23;
$end_min    = isset($_POST['end_min'])    ? (int)$_POST['end_min']    : 59;









// Find local time

$server_time_full = shell_exec('timedatectl');
$server_time_lines = explode("\n", trim($server_time_full));
$server_time = $server_time_lines[0] ?? '';








// Headers for cPanel/WHM or CWP

if ($isCPanelServer) {
    require_once('/usr/local/cpanel/php/WHM.php');
    WHM::header('imh-sys-snap WHM Interface', 0, 0);
} else {
    echo '<div class="panel-body" style="padding-bottom: 5px; display: block;">';
};








// Styles for the tabs and buttons

echo '<style>
.sys-snap-tables {
    border-collapse: collapse;
    margin: 2em 0;
    background: #fafcff;
}

.sys-snap-tables,
.sys-snap-tables th,
.sys-snap-tables td {
  border: 1px solid #000;
}

.sys-snap-tables th,
.sys-snap-tables td {
  padding: 4px 8px;
}

.sys-snap-tables thead {
    background:#e6f2ff; 
    color:#333;
    font-weight: 600;
}

.tabs-nav {
    display: flex;
    border-bottom: 1px solid #e3e3e3;
    margin-bottom: 2em;
}

.tabs-nav button {
    border: none;
    background: #f8f8f8;
    color: #333;
    padding: 12px 28px;
    cursor: pointer;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
    font-size: 1em;
    margin-bottom: -1px;
    border-bottom: 2px solid transparent;
    transition: background 0.15s, border-color 0.15s;
}

.tabs-nav button.active {
    background: #fff;
    border-bottom: 2px solid #0077C9;
    color: #0077C9;
    font-weight: 600;
}

.tab-content { display: none; }

.tab-content.active { display: block; }
</style>';







// Plugin icon and title

$img_src = $isCPanelServer ? 'imh-sys-snap.png' : 'design/img/imh-sys-snap.png';
echo '<h1 style="margin:2em 0 2em 0;"><img src="' . htmlspecialchars($img_src) . '" alt="sys-snap" style="margin-right:1em;" />System Snapshot (sys-snap)</h1>';








// This is the tab selector for the two main sections: sys-snap and 24-hour statistics.

echo '<div class="tabs-nav" id="imh-tabs-nav">
    <button type="button" class="active" data-tab="tab-sys-snap" aria-label="System Snapshot tab">System Snapshot</button>
    <button type="button" data-tab="tab-loadavg" aria-label="24 Hour Statistics tab">24 Hour Statistics</button>
</div>';









// Tab selector script

echo "<script>
document.querySelectorAll('#imh-tabs-nav button').forEach(function(btn) {
    btn.addEventListener('click', function() {
        // Remove 'active' class from all buttons and tab contents
        document.querySelectorAll('#imh-tabs-nav button').forEach(btn2 => btn2.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        // Activate this button and the corresponding tab
        btn.classList.add('active');
        var tabId = btn.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});
</script>";











// Handle POST: start sys-snap action

$status_cmd = '/usr/bin/perl /opt/imh-sys-snap/bin/sys-snap.pl --check 2>&1';
$allowed_actions = ['start']; // And perhaps 'stop'
$action_output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], $allowed_actions, true)) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $action = $_POST['action'];
    //'Stop' didn't work so good in testing.
    if ($action === 'stop') {
        $stop_cmd = "echo y | /usr/bin/perl /opt/imh-sys-snap/bin/sys-snap.pl --stop 2>&1";
        $action_output = shell_exec($stop_cmd);
    } elseif ($action === 'start') {
        $start_cmd = "echo y | /usr/bin/perl /opt/imh-sys-snap/bin/sys-snap.pl --start 2>&1";
        $action_output = shell_exec($start_cmd);
    }
}

// Check current status
$status_output = shell_exec($status_cmd);
$is_running = false;
$pid = null;

if (preg_match("/Sys-snap is running, PID:\s*'(\d+)'/", $status_output, $m)) {
    $is_running = true;
    $pid = $m[1];
}







// Main sys-snap tab content begins

echo '<div id="tab-sys-snap" class="tab-content active">';





// Status box

echo '<div style="margin:2em 0 2em 0; padding:1em; border:1px solid #ccc; border-radius:8px; display:block;">';
if ($is_running) {
    echo '<span style="display:inline-block; background:#e6ffee; color:#26a042; padding:6px 18px; border-radius:14px; font-weight:600; margin-right:18px; border:1px solid #8fd19e;">';
    echo 'Running';
    echo '</span>';
    echo "<span style='color:#888'>PID: " . intval($pid) . '</span>';
    
} else {
    echo '<span style="display:inline-block; background:#ffeaea; color:#c22626; padding:6px 18px; border-radius:14px; font-weight:600; margin-right:18px; border:1px solid #e99;">';
    echo 'Not Running';
    echo '</span>';

    // Start button.
    echo '
<form method="post" style="display:inline;">
  <input type="hidden" name="csrf_token" value="' . htmlspecialchars($CSRF_TOKEN) . '">
  <input type="hidden" name="form"       value="sys_snap_control">
  <input type="hidden" name="action"     value="start"> 
  <button type="submit">Start sys-snap</button>
</form>
    ';
}
echo '</div>';






// System output, if the button was used to start sys-snap

if ($action_output) {
    echo "<pre style='background:#f8f8f8;border:1px solid #ccc;padding:1em;margin:2em;'>"
        . htmlspecialchars($action_output) . "</pre>";
}












// Info box

echo "<div style='margin-bottom:1em; padding:1em; border:1px solid #ccc; border-radius:8px; display:block;'><p><a target='_blank' href='https://github.com/CpanelInc/tech-SysSnapv2'>sys-snap</a> logs CPU and memory usage on a rolling 24-hour cycle, every minute.</p><p>Review the <code>24 Hour Statistics</code> to identify time ranges of interest.</p><br/>";
echo "<p><strong>CPU Score</strong>: 1 = 1% of a CPU<br/>
<strong>Memory Score</strong>: 1 = 1% of total memory</p>";
echo "</div>";







// Set Time Range form

echo '<form method="post" style="margin:2em 0; padding:1em; border:1px solid #ccc; border-radius:8px; display:block;">
<input type="hidden" name="csrf_token" value="' . htmlspecialchars($CSRF_TOKEN) .'">
<input type="hidden" name="form" value="time_range">
';
echo "<p style='margin-left:1em; color:#444; font-weight:600;'>" . htmlspecialchars($server_time) . "</p>";

echo 'Start: <select name="start_hour">';
for ($i=0; $i<24; $i++) echo "<option value='$i'" . ($i==$start_hour?' selected':'') . ">$i</option>";
echo '</select> : <select name="start_min">';
for ($i=0; $i<60; $i++) echo "<option value='$i'" . ($i==$start_min?' selected':'') . ">" . str_pad($i,2,'0',STR_PAD_LEFT) . "</option>";
echo '</select>';

echo ' &nbsp; End: <select name="end_hour">';
for ($i=0; $i<24; $i++) echo "<option value='$i'" . ($i==$end_hour?' selected':'') . ">$i</option>";
echo '</select> : <select name="end_min">';
for ($i=0; $i<60; $i++) echo "<option value='$i'" . ($i==$end_min?' selected':'') . ">" . str_pad($i,2,'0',STR_PAD_LEFT) . "</option>";
echo '</select>';

echo ' <input type="submit" name="set_time" value="Set Time Range" style="margin-left:15px;padding:5px 15px; border-radius:6px;">';
echo '</form>';









//Sys-snap output

echo '<h2 style="margin-top:2em;">Scores from ' . sprintf('%02d:%02d', $start_hour, $start_min) . ' to ' . sprintf('%02d:%02d', $end_hour, $end_min) . '</h2>';

$sys_snap_cmd = sprintf(
    '/usr/bin/perl /opt/imh-sys-snap/bin/sys-snap.pl --print %d:%02d %d:%02d -v 2>&1',
    (int)$start_hour, (int)$start_min, (int)$end_hour, (int)$end_min
);
$output = shell_exec($sys_snap_cmd);

if (!$output) {
    echo "<div style='margin-top:2em;' class='alert alert-danger'>Could not get output from sys-snap.<br/>Check if the script is running and accessible.</div>";
    WHM::footer();
    exit;
}








// Parse output

function parseSysSnap($text) {
    $lines = explode("\n", $text);
    $results = [];
    $currentUser = null;
    $currentSection = null;

    foreach ($lines as $line) {
        $trim = trim($line);

        // User Section
        if (preg_match('/^user:\s+(\S+)/', $line, $m)) {
            $currentUser = $m[1];
            $results[$currentUser] = [
                'cpu-score' => null, 'cpu-list' => [],
                'memory-score' => null, 'memory-list' => []
            ];
            $currentSection = null;
            continue;
        }

        // CPU score
        if (preg_match('/cpu-score:\s+([0-9\.]+)/', $line, $m) && $currentUser) {
            $results[$currentUser]['cpu-score'] = $m[1];
            $currentSection = 'cpu-list';
            continue;
        }

        // memory score
        if (preg_match('/memory-score:\s+([0-9\.]+)/', $line, $m) && $currentUser) {
            $results[$currentUser]['memory-score'] = $m[1];
            $currentSection = 'memory-list';
            continue;
        }

        // CPU process
        if (preg_match('/C:\s*([0-9\.]+)\s*proc:\s*(.*)$/', $trim, $m) && $currentUser && $currentSection == 'cpu-list') {
            $results[$currentUser]['cpu-list'][] = ['score' => $m[1], 'proc' => $m[2]];
            continue;
        }

        // Memory process
        if (preg_match('/M:\s*([0-9\.]+)\s*proc:\s*(.*)$/', $trim, $m) && $currentUser && $currentSection == 'memory-list') {
            $results[$currentUser]['memory-list'][] = ['score' => $m[1], 'proc' => $m[2]];
            continue;
        }
    }

    return $results;
}








// Display sys-snap data

$data = parseSysSnap($output);

// --- User Summary Table ---
echo '<div style="margin:2em 0 2em 0;">';
echo '<table class="sys-snap-tables">';
echo '<thead style="background:#e6f2ff; color:#333;">';
echo '<th>User</th>';
echo '<th>CPU Score</th>';
echo '<th>Memory Score</th>';
echo '</thead>';
foreach ($data as $user => $vals) {
    // Anchor ID for the user section
    $anchor = 'user-' . rawurlencode($user);
    static $row_idx = 0;
    $row_class = ($row_idx % 2 === 1) ? " style='background:#f4f4f4;'" : "";
    echo "<tr$row_class>";
    $row_idx++;
    if ($isCPanelServer) {
        echo '<td><strong><a href="#' . $anchor . '">' . htmlspecialchars($user) . '</a></strong></td>';
    } else {
        echo '<td><strong>' . htmlspecialchars($user) . '</strong></td>';
    }
    echo '<td style="text-align:right;">' . htmlspecialchars($vals['cpu-score']) . '</td>';
    echo '<td style="text-align:right;">' . htmlspecialchars($vals['memory-score']) . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';

echo '<div style="font-family: monospace; margin-top:2em;">';
foreach ($data as $user => $vals) {
    $anchor = 'user-' . rawurlencode($user);
    echo "<div id='$anchor' style='display:block; margin-top:3em; padding:0.5em 1em; border-top: 1px solid black;'>
    <h2 style='scroll-margin-top:70px;'>User: <span style='color:rgb(42, 73, 94);'>" . htmlspecialchars($user) . "</span></h2>";

    // CPU
    echo "<h3>CPU Score: " . htmlspecialchars($vals['cpu-score']) . "</h3><br/>";
    echo "<table class='sys-snap-tables'>";
    echo "<thead><th>CPU</th><th>Process</th></thead>";
    foreach ($vals['cpu-list'] as $row) {
        static $cpu_row_idx = 0;
        $cpu_row_class = ($cpu_row_idx % 2 === 1) ? " style='background:#f4f4f4;'" : "";
        echo "<tr$cpu_row_class>";
        $cpu_row_idx++;
        echo "<td style='text-align:right;'>" . htmlspecialchars($row['score']) . "</td>";
        echo "<td>" . htmlspecialchars($row['proc']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Memory
    echo "<br/><h3>Memory Score: " . htmlspecialchars($vals['memory-score']) . "</h3><br/>";
    echo "<table class='sys-snap-tables'>";
    echo "<thead><th>Memory</th><th>Process</th></thead>";
    foreach ($vals['memory-list'] as $row) {
        static $mem_row_idx = 0;
        $mem_row_class = ($mem_row_idx % 2 === 1) ? " style='background:#f4f4f4;'" : "";
        echo "<tr$mem_row_class>";
        $mem_row_idx++;
        echo "<td style='text-align:right'>" . htmlspecialchars($row['score']) . "</td>";
        echo "<td>" . htmlspecialchars($row['proc']) . "</td>";
        echo "</tr>";
    }
    echo "</table>
    </div>";
}
echo "</div>";





//End of sys-snap tab content
echo "</div>";











// '24 Hour Statistics' tab, from sar -q and sar -B

function getSarQData() {
    $shortName = exec('date +%Z');
    $longName = timezone_name_from_abbr($shortName);
    date_default_timezone_set($longName);

    $now = time(); // current unix time
    $today = date('d', $now); // day-of-month
    $yesterday = date('d', strtotime('yesterday', $now));
    $cur_time = date('H:i:s', $now); // current time in 24h

    $cmd1 = "LANG=C sar -q -f /var/log/sa/sa$yesterday -s $cur_time";
    $cmd2 = "LANG=C sar -q -f /var/log/sa/sa$today -e $cur_time";
    $out1 = shell_exec($cmd1);
    $out2 = shell_exec($cmd2);

    // Merge two arrays of lines
    function merge_sar($out1, $out2) {
        $lines1 = array_filter(array_map('trim', explode("\n", $out1)));
        $lines2 = array_filter(array_map('trim', explode("\n", $out2)));
        // Remove headers from lines2
        foreach ($lines2 as $k => $v) {
            if (strpos($v, 'runq-sz') !== false) {
                unset($lines2[$k]);
            }
        }
        return array_merge($lines1, $lines2);
    }

    $all_lines = merge_sar($out1, $out2);

    // Find the header BEFORE filtering
    $header = null;
    foreach ($all_lines as $idx => $line) {
        if (preg_match('/runq-sz\s+plist-sz\s+ldavg-1\s+ldavg-5\s+ldavg-15\s+blocked/', $line)) {
            $temp_header = preg_split('/\s+/', trim($line));
            $header = array_merge(['Time'], array_slice($temp_header, 1)); // Prepend "Time"
            break;
        }
    }

    if (!$header) {
        // Fallback: hard-code for sar -q
        $header = ['Time', 'runq-sz', 'plist-sz', 'ldavg-1', 'ldavg-5', 'ldavg-15', 'blocked'];
    }

    // Now filter out junk/headers
    function filter_sar_data_lines($lines) {
        $data = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if (
                !$trim
                || strpos($trim, 'Average:') === 0
                || strpos($trim, 'Linux') === 0
                || preg_match('/runq-sz\s+plist-sz\s+ldavg-1/', $trim)
            ) {
                continue;
            }
            $data[] = $trim;
        }
        return $data;
    }

    $all_lines_filtered = filter_sar_data_lines($all_lines);

    if (!$all_lines_filtered) return false;

    // Now $all_lines_filtered are just data rows
    $lines = $all_lines_filtered;

    $data = [];
    foreach ($lines as $line) {
        $row = preg_split('/\s+/', $line);
        if (empty($row) || count($row) < count($header)) continue;
        // (Optional: handle AM/PM time formats if needed)
        $time = $row[0];
        $rest = array_slice($row, 1, count($header)-1);
        if (count($rest) == count($header)-1) {
            $data[] = array_combine($header, array_merge([$time], $rest));
        }
    }
    return [$header, $data];
}






// Secondary function to get sar -B data

function getSarBData($cur_time, $yesterday, $today) {
    $cmd1 = "LANG=C sar -B -f /var/log/sa/sa$yesterday -s $cur_time";
    $cmd2 = "LANG=C sar -B -f /var/log/sa/sa$today -e $cur_time";
    $out1 = shell_exec($cmd1);
    $out2 = shell_exec($cmd2);

    // Merge and filter lines
    function merge_b($out1, $out2) {
        $lines1 = array_filter(array_map('trim', explode("\n", $out1)));
        $lines2 = array_filter(array_map('trim', explode("\n", $out2)));
        foreach ($lines2 as $k => $v) {
            if (strpos($v, 'pgpgin/s') !== false) unset($lines2[$k]);
        }
        return array_merge($lines1, $lines2);
    }

    $all_lines = merge_b($out1, $out2);

    // Find header BEFORE filtering
    $header = null;
    foreach ($all_lines as $idx => $line) {
        if (preg_match('/pgpgin\/s\s+pgpgout\/s\s+fault\/s\s+majflt\/s/', $line)) {
            $header = preg_split('/\s+/', trim($line));
            array_unshift($header, 'Time');
            break;
        }
    }
    if (!$header) {
        $header = ['Time','pgpgin/s','pgpgout/s','fault/s','majflt/s','pgfree/s','pgscank/s','pgscand/s','pgsteal/s','%vmeff'];
    }

    // Filter out junk/headers
    function filter_b($lines) {
        $data = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if (!$trim
                || strpos($trim, 'Average:') === 0
                || strpos($trim, 'Linux') === 0
                || preg_match('/pgpgin\/s\s+pgpgout\/s\s+fault\/s/', $trim))
                continue;
            $data[] = $trim;
        }
        return $data;
    }

    $data_lines = filter_b($all_lines);

    $data = [];
    foreach ($data_lines as $line) {
        $row = preg_split('/\s+/', $line);
        if (count($row) >= count($header)-1) {
            // only take first n columns
            $vals = array_slice($row,0,count($header)-1);
            $data[] = array_combine(array_slice($header,0,count($header)-1), $vals);
        }
    }
    return [$header, $data];
}







// Merge sar -q and sar -B data by time

function mergeSarQandB($sarqData, $sarBData) {
    // Build lookup for sar -B
    $b_map = [];
    foreach ($sarBData as $row) {
        $b_map[$row['Time']] = $row;
    }

    $merged = [];
    foreach ($sarqData as $qrow) {
        $time = $qrow['Time'];
        $brow = isset($b_map[$time]) ? $b_map[$time] : null;
        $merged_row = $qrow;
        if ($brow) {
            // Only append desired columns, e.g. pgpgin/s, pgpgout/s, fault/s, majflt/s
            foreach (['pgpgin/s','pgpgout/s','fault/s','majflt/s'] as $col) {
                $merged_row[$col] = $brow[$col];
            }
        } else {
            // No B data for this time
            foreach (['pgpgin/s','pgpgout/s','fault/s','majflt/s'] as $col) {
                $merged_row[$col] = '';
            }
        }
        $merged[] = $merged_row;
    }
    return $merged;
}





// Explanation block for the 24 Hour Statistics tab

echo '<div id="tab-loadavg" class="tab-content">';
echo "<div style='margin:1em 0 1em 0; padding:1em; border:1px solid #ccc; border-radius:8px; display:block;'><p><code>sar</code> collects, reports and saves system activity information (<a href='https://github.com/sysstat/sysstat' target='_blank'>sysstat</a>)</p>";
echo "<h2>Queue length and load average statistics (<code>sar -q</code>)</h2>";
echo "<p>
<strong>runq-sz</strong> - Number of processes waiting for run time (run queue size)<br/>
<strong>plist-sz</strong> - Number of processes in the process list<br/>
<strong>ldavg-1</strong> - System load average for the last 1 minute<br/>
<strong>ldavg-5</strong> - System load average for the last 5 minutes<br/>
<strong>ldavg-15</strong> - System load average for the last 15 minutes<br/>
<strong>blocked</strong> - Number of processes currently blocked, waiting for I/O<br/>
<h2>Paging statistics (<code>sar -B</code>)</h2><p>
<strong>pgpgin/s</strong> - Kilobytes paged in from disk per second<br/>
<strong>pgpgout/s</strong> - Kilobytes paged out to disk per second<br/>
<strong>fault/s</strong> - Number of page faults per second<br/>
<strong>majflt/s</strong> - Number of major page faults per second (requiring disk access)
</p>";
echo "</div>";










// Get sar -q and sar -B data

list($header_q, $sarq) = getSarQData();

if (!$sarq) {
    echo "<div style='color: #c00; margin:1em;'>Could not get sar -q data.</div>";
} else {
    // Prepare for sar -B
    $now = time();
    $today = date('d', $now);
    $yesterday = date('d', strtotime('yesterday', $now));
    $cur_time = date('H:i:s', $now);

    list($header_b, $sarb) = getSarBData($cur_time, $yesterday, $today);

    // Merge by time
    $merged = mergeSarQandB($sarq, $sarb);

    // New header
    $final_header = array_merge($header_q, array_diff(['pgpgin/s','pgpgout/s','fault/s','majflt/s'], $header_q));

    // Output table
    echo "<table class='sys-snap-tables'>";
    echo "<thead>";
    foreach ($final_header as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</thead>";
    foreach ($merged as $row) {
        echo "<tr>";
        // Alternate even/odd rows, odd rows get light gray background
        $row_class = ($row_idx ?? 0) % 2 === 1 ? " style='background:#f4f4f4;'" : "";
        echo "<tr$row_class>";
        foreach ($final_header as $col) {
            echo "<td style='text-align:right'>" . htmlspecialchars(isset($row[$col]) ? $row[$col] : '') . "</td>";
        }
        echo "</tr>";
        $row_idx = isset($row_idx) ? $row_idx + 1 : 1;
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='font-size:0.9em; color:#555;'>All values are from the most recent sar -q and sar -B samples.</p>";
}

echo '</div>';










// Plugin footer, for all tabs

echo '<div style="margin:2em 0 2em 0; padding:1em; border:1px solid #ccc; border-radius:8px; display:block;"><img src="' . htmlspecialchars($img_src) . '" alt="sys-snap" style="margin-bottom:1em;" /><p><a href="https://github.com/CpanelInc/tech-SysSnapv2" target="_blank">sys-snap</a> by Bryan Christensen.</p><p>Plugin by <a href="https://inmotionhosting.com" target="_blank">InMotion Hosting</a>.</p></div>';










// Plugin Footer

if ($isCPanelServer) {
    WHM::footer();
} else {
    echo '</div>';
};

?>