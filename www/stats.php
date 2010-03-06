<?php

// Obtain common includes
require_once '../include/prepend.php';

response_header('Bugs Stats');

$titles = array(
	'Closed'	=> 'Closed',
	'Open'		=> 'Open',
	'Critical'	=> 'Crit',
	'Verified'	=> 'Verified',
	'Analyzed'	=> 'Analyzed',
	'Assigned'	=> 'Assigned',
	'Feedback'	=> 'Fdbk',
	'No Feedback'	=> 'No&nbsp;Fdbk',
	'Suspended'	=> 'Susp',
	'Bogus'		=> 'Bogus',
	'Duplicate'	=> 'Dupe',
	'Wont fix'	=> 'Wont&nbsp;Fix',
);

$rev = isset($_GET['rev']) ? $_GET['rev'] : 1;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'Open';
$total = 0;
$row = array();
$pkg = array();
$pkg_tmp = array();
$pkg_total = array();
$pkg_names = array();
$all = array();
$pseudo	= true;
$pseudo_pkgs = get_pseudo_packages($site);

if (!array_key_exists($sort_by, $titles)) {
	$sort_by = 'Open';
}

$where = '';
if (empty($_GET['bug_type']) || $_GET['bug_type'] == 'All') {
	$bug_type = 'All';
} else {
	$bug_type = $_GET['bug_type'];
	$where = ' AND bug_type = ' . $dbh->quote($bug_type);
}

$query  = "
	SELECT b.package_name, b.status, COUNT(*) AS quant
	FROM bugdb AS b
	WHERE 1 = 1 {$where}
	GROUP BY b.package_name, b.status
	ORDER BY b.package_name, b.status
";

$result = $dbh->prepare($query)->execute();

while ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
	$pkg_tmp[$row['status']][$row['package_name']] = $row['quant'];
	@$pkg_total[$row['package_name']] += $row['quant'];
	@$all[$row['status']] += $row['quant'];
	@$total += $row['quant'];
	$pkg_names[$row['package_name']] = 0;
}

if (count($pkg_tmp)) {
	foreach ($titles as $key => $val) {
		if (isset($pkg_tmp[$key]) && is_array($pkg_tmp[$key])) {
			$pkg[$key] = array_merge($pkg_names, $pkg_tmp[$key]);
		} else {
			$pkg[$key] = $pkg_names;
		}
	}
}

if ($total > 0) {
	if ($rev == 1) {
		arsort($pkg[$sort_by]);
	} else {
		asort($pkg[$sort_by]);
	}
}
?>

<form method="get" action="stats.php">
	<table>
		<tr>
			<td style="white-space: nowrap">
				<strong>Bug Type:</strong>
				<select class="small" id="bug_type" name="bug_type" onchange="this.form.submit(); return false;">
					<?php show_type_options($bug_type, true) ?>
				</select>
				<input class="small" type="submit" name="submitStats" value="Search" />
			</td>
		</tr>
	</table>
</form>

<table style="width: 100%; margin-top: 1em;">

<?php // Exit if there are no bugs for this version

if ($total == 0) {
	echo '<tr><td>No bugs found</td></tr></table>' . "\n";
	response_footer();
	exit;
}

echo display_stat_header($total, true);

echo <<< OUTPUT
	<tr>
		<td class="bug_head">All</td>
		<td class="bug_bg0">{$total}</td>
OUTPUT;

$i = 1;
foreach ($titles as $key => $val) {
	echo '		<td class="bug_bg' , $i++ % 2 , '">';
	echo bugstats($key, 'all') , "</td>\n";
}
echo "	</tr>\n";

$stat_row = 1;
foreach ($pkg[$sort_by] as $name => $value) {
	if ($name != 'all') {
		// Output a new header row every 40 lines
		if (($stat_row++ % 40) == 0) {
			echo display_stat_header($total, false);
		}
		echo <<< OUTPUT
	<tr>
		<td class="bug_head">{$name}</td>
		<td class="bug_bg0">{$pkg_total[$name]}</td>
OUTPUT;

		$i = 1;
		foreach ($titles as $key => $val) {
			echo '		<td class="bug_bg', $i++ % 2, '">';
			echo bugstats($key, $name), "</td>\n";
		}
		echo "	</tr>\n";
	}
}

echo "</table>\n";

response_footer();

// Helper functions

function bugstats($status, $name)
{
	global $pkg, $all, $bug_type;

	if ($name == 'all') {
		if (isset($all[$status])) {
			return '<a href="search.php?cmd=display&amp;' .
				   'bug_type='.$bug_type.'&amp;status=' .$status .
				   '&amp;by=Any&amp;limit=30">' .
				   $all[$status] . "</a>\n";
		}
	} else {
		if (empty($pkg[$status][$name])) {
			return '&nbsp;';
		} else {
			return '<a href="search.php?cmd=display&amp;'.
				   'bug_type='.$bug_type.'&amp;status=' .
				   $status .
				   '&amp;package_name%5B%5D=' . urlencode($name) .
				   '&amp;by=Any&amp;limit=30">' .
				   $pkg[$status][$name] . "</a>\n";
		}
	}
}

function sort_url($name)
{
	global $sort_by, $rev, $phpver, $category, $developer, $titles;

	if ($name == $sort_by) {
		$reve = ($rev == 1) ? 0 : 1;
	} else {
		$reve = 1;
	}
	if ($sort_by != $name) {
		$attr = 'class="bug_stats"';
	} else {
		$attr = 'class="bug_stats_choosen"';
	}
	return '<a href="./stats.php?sort_by=' . urlencode($name) .
		   '&amp;rev=' . $reve . '&amp;category=' . $category .
		   '&amp;developer=' . $developer . '" ' . $attr . '>' .
		   $titles[$name] . '</a>';
}

function display_stat_header($total, $grandtotal = true)
{
	global $titles;

	$stat_head  = " <tr>\n";
	if ($grandtotal) {
		$stat_head .= '  <th class="bug_header">Name</th>' . "\n";
	} else {
		$stat_head .= '  <th class="bug_header">&nbsp;</th>' . "\n";
	}
	$stat_head .= '  <th class="bug_header">&nbsp;</th>' . "\n";

	foreach ($titles as $key => $val) {
		$stat_head .= '  <th class="bug_header">' . sort_url($key) . "</th>\n";
	}

	$stat_head .= '</tr>' . "\n";
	return $stat_head;
}

