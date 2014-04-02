<a name="reports"></a>
<a href="#reports">
    <div class="block_title">Reports</div>
</a>
<div class="graph_block">
<?php
$graph_args = "env=$env&c=$c";
if (isset($dn)) { $graph_args = "$graph_args&dn=$dn"; }

if (isset($g)) { $graph_reports = array($g); }
else { $graph_reports = find_dashboards($env, $c); }

foreach ($graph_reports as $graph_report) {
    $current_graph_args = $graph_args . "&h=$h";
    print print_zoom_graph($current_graph_args, "g=$graph_report", $z, $from, $until);
}
?>
</div>
