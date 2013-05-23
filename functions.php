<?php

#------------------------------------------------------------------------------
# Functions to make strings compatible
function clean_string( $string ) {
    return htmlentities( $string );
}
function sanitize ( $string ) {
    return escapeshellcmd( clean_string( rawurldecode( $string ) ) ) ;
}
function sanitize_datetime ( $dt ) {
    if (preg_match("/^(\d{4}[-\/]\d{2}[-\/]\d{2}|\d{2}[-\/]\d{2}[-\/]\d{4}) \d{1,2}:\d{2}/", $dt)) {
        return date('H:i_Ymd', strtotime($dt));
    }
    else {
        return $dt;
    }
}

#------------------------------------------------------------------------------
# Function to print options for dropdown menus
function print_dropdown_menus($options, $choice, $default) {
    if ( $default != "" ) {
        $option_values = "                <option value=\"\">$default</option>\n";
    }
    foreach ($options as $option) {
        if ($option == $choice) {
            $selected = "selected=\"selected\"";
        }
        else {
            $selected = "";
        }
        $option_values .= "                <option value=\"$option\" $selected>$option</option>\n";
    }
    return $option_values;
}


#------------------------------------------------------------------------------
# Function to return graph  domainname.
function get_graph_domainname() {
    global $conf;

    if ($conf['use_random_graph_domainname'])
        return str_replace("//", "//img" . rand(10,80) . ".", $conf['graph_domainname']);

    return $conf['graph_domainname'];
}

#------------------------------------------------------------------------------
# Function to build a Graphite url based on a json report.
function build_graphite_series( $config, $host_cluster = "" ) {
    $targets = array();
    $colors = array();
    $function = array();
    // Keep track of stacked items
    $stacked = 0;
    $pie = 0;

    foreach( $config[ 'series' ] as $item ) {
        if ( $item['type'] == "stack" )
            $stacked++;
        if ( $item['type'] == "pie" )
            $pie++;
        if ( isset($item['functions']) )
            $functions = $item['functions'];
        else
            $functions[0] = "sumSeries";
        if ( isset($item['hostname']) && isset($item['clustername']) )
            $host_cluster = $item['clustername'] . "." . str_replace(".","_", $item['hostname']);
        $metric = "$host_cluster.${item['metric']}";
        foreach( $functions as $function ) {
            $metric = "$function($metric)";
        }

        $targets[] = "target=". urlencode( "alias($metric,'${item['label']}')" );
        $colors[] = $item['color'];
    }

    $output = implode( $targets, '&' );
    $output .= "&colorList=" . implode( $colors, ',' );
    $output .= "&vtitle=" . urlencode( isset($config[ 'vertical_label' ]) ? $config[ 'vertical_label' ] : "" );

    if ( isset($config['units']) )
        $output .= "&yUnitSystem=" . $config['units'];

    // Do we have any stacked elements. We assume if there is only one element
    // that is stacked that rest of it is line graphs
    if ( $stacked > 0 ) {
        if ( $stacked > 1 )
            $output .= "&areaMode=stacked";
        else
            $output .= "&areaMode=first";
    }
    elseif ( $pie > 0 ) {
        $output .= "&graphType=pie";
    }

    return $output;
}

#------------------------------------------------------------------------------
# Functions for printing graph (cards)
function print_graph($args, $metric_report, $graph_size, $from, $until) {
    global $conf;
    $width  = $conf['graph_sizes'][$graph_size]['width'];
    $height = $conf['graph_sizes'][$graph_size]['height'];

    $graph_html = "
      <div class=\"graph_card\">
        <div class=\"graph_img\">
          <a href=\"?$args&from=$from&until=$until\">
            <img width=\"$width\" height=\"$height\" class=\"lazy\" src=\"img/blank.gif\" data-original=\"". get_graph_domainname() . "/graph.php?$args&$metric_report&z=$graph_size&from=$from&until=$until\" />
          </a>
        </div>
        " . show_graph_buttons("$args&$metric_report", $from, $until) . "</div>";
    return $graph_html;
}

function print_zoom_graph($args, $metric_report, $graph_size, $from, $until) {
    global $conf;
    $width  = $conf['graph_sizes'][$graph_size]['width'];
    $height = $conf['graph_sizes'][$graph_size]['height'];

    $graph_html = "
      <div class=\"graph_card\">
        <div class=\"graph_img\">
          <a href=\"graph.php?$args&$metric_report&from=$from&until=$until&z=xlarge\">
            <img width=\"$width\" height=\"$height\" class=\"lazy\" src=\"img/blank.gif\" data-original=\"". get_graph_domainname() . "/graph.php?$args&$metric_report&z=$graph_size&from=$from&until=$until\" />
          </a>
        </div>
        " . show_graph_buttons("$args&$metric_report", $from, $until) . "</div>";
    return $graph_html;
}

function show_graph_buttons($args, $from, $until) {
    $button_html = "<div class=\"graph_buttons\">
          <a href=\"graph_all_periods.php?$args\">
            <img src=\"img/periods_holo_16.png\" width=\"16\" height=\"16\" title=\"Show periodic graphs\">
          </a>
          <a href=\"graph.php?$args&from=$from&until=$until&z=xlarge\">
            <img src=\"img/zoom_holo_16.png\" width=\"16\" height=\"16\" title=\"Show XL graph\">
          </a>
        </div>
      ";
    return $button_html;
}

function print_period_graph($args, $metric_report, $timeframe) {
    global $conf;
    $width  = $conf['graph_sizes']["large"]['width'];
    $height = $conf['graph_sizes']["large"]['height'];

    $graph_html = "
      <div class=\"graph_card\">
        <div class=\"graph_img\">
          <img width=\"$width\" height=\"$height\" class=\"lazy\" src=\"img/blank.gif\" data-original=\"". get_graph_domainname() . "/graph.php?$args&$metric_report&z=large&st=$timeframe+ago\" />
        </div>
      </div>
      ";
    return $graph_html;
}


#------------------------------------------------------------------------------
# Finds the max over a set of metric graphs.
function find_limits($environment, $cluster, $metricname, $start, $end) {
    global $conf;

    $max=0;
    $target = $conf['graphite_prefix'] . "$environment.$cluster.*." . $metricname;
    $raw_data = file_get_contents($conf['graphite_render_url'] . "?target=$target&from=$start&until=$end&format=json");
    $data = json_decode($raw_data, TRUE);
    $maxdatapoints = array();
    foreach ( $data as $data_target ) {
        $highestMaxDatapoints = $data_target['datapoints'];
        foreach ( $highestMaxDatapoints as $datapoint ) {
            array_push($maxdatapoints, $datapoint[0]);
        }
    }
    sort($maxdatapoints);
    $max = round(max($maxdatapoints) * 1.1);
    return $max;
}

#------------------------------------------------------------------------------
# Finds dashboards to specific environment/cluster
function find_dashboards($environment, $cluster="") {
    global $conf;

    $graph_reports = array();
    $dash_config = json_decode(file_get_contents($conf['dashboard_config']), TRUE);
    foreach ($dash_config['dashboards'] as $dash) {
        if (! preg_match($dash['environments'], $environment) ) {
            continue;
        }
        if ($cluster) {
            if (! preg_match($dash['clusters'], $cluster)){
                continue;
            }
        }
        foreach ($dash['included_reports'] as $dashboard) {
            array_push($graph_reports, $dashboard);
        }
    }
    return $graph_reports;
}

#------------------------------------------------------------------------------
# Determines of report graphs should be shows in this dashboard
function show_on_dashboard($report_name, $environment, $cluster) {
    global $conf;

    $dash_config = json_decode(file_get_contents($conf['dashboard_config']), TRUE);
    foreach ($dash_config['dashboards'] as $dash) {
        if ( preg_match($dash['environments'], $environment) && preg_match($dash['clusters'], $cluster) ){
            if ( in_array($report_name, $dash['included_reports']) ) {
                return True;
            }
        }
    }
    return False;
}

#------------------------------------------------------------------------------
# Find graphite metrics matching regex
function find_metrics($search_string, $group_depth=0) {
    global $conf;

    $metrics = array();
    $search_url = $conf['graphite_url_base'] . "/metrics/expand/?leavesOnly=1";
    $search_prefix = quotemeta($conf['graphite_prefix'] . $search_string);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $search_query_array = array();
    $search_query_wildcard = "";
    $i = 0;
    while ($i <= 10) {
        $search_query_wildcard = $search_query_wildcard . ".*";
        $search_query_item = "&query=" . $search_prefix . $search_query_wildcard;
        array_push($search_query_array, $search_query_item);
        $i++;
    }
    $search_query = implode("", $search_query_array);
    curl_setopt($ch, CURLOPT_URL, $search_url . $search_query);
    $results = json_decode(curl_exec($ch), TRUE);
    foreach ($results['results'] as $metric) {
        $metric_string = preg_replace("/^$search_prefix\./", "", $metric);
        $arr = explode('.',trim($metric_string));
        $metric_group = join(".", array_slice($arr, 0, $group_depth));
        if (!isset($metrics[$metric_group]) )
            $metrics[$metric_group] = array();
        array_push($metrics[$metric_group], $metric_string);
    }

    curl_close($ch);
    return $metrics;
}

?>
