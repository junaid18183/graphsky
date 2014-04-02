<a name="overview"></a>
<a href="#overview">
    <div class="block_title">Overview</div>
</a>
<div class="graph_block">
<?php
$graph_args = "env=$env&c=$c&l=$l";

if (isset($g)) { $graph_reports = array($g); }
elseif (isset($m)) { $metric_graph = $m; }
else { $graph_reports = find_dashboards($env, $c); }

if (isset($m)) {
    print print_zoom_graph($graph_args, "m=$metric_graph", $z, $from, $until);
}
elseif (isset($graph_reports)) {
    foreach ($graph_reports as $graph_report) {
        print print_zoom_graph($graph_args, "g=$graph_report", $z, $from, $until);
    }
}
?>
</div>
