<?php

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__) . DS);

function appendStringToFile($filename, $string) {
	$fh = fopen($filename, 'a');
	$string = $string . "\n";
	fwrite($fh, $string);
	fclose($fh);
}

function addSeparatorToTemperature($temperature) {
	#if $temperature is not zero add comma
	if(strlen($temperature) !== 1) {
		$temperature = substr_replace($temperature, '.' . substr($temperature, -1), -1);
	}
	
	return $temperature;
}

function returnChartistString(Array $filenameArray) {
	$chartString = '';
	$labelsArray = Array();
	$seriesNamesArray = Array();
	$dataArray = Array();
	$labelString = '';
	$seriesStringsArray = Array();
	
	foreach($filenameArray as $filename=>$label) {
		if(file_exists($filename)) {
			$fileContent = file_get_contents($filename);
			$contentArray = explode("\n", $fileContent);
			
			foreach($contentArray as $line) {
				if(!empty($line)) {
					$lineArray = explode(';', $line);
					
					$date = $lineArray[0];
					$name = $lineArray[1];
					if($label) {
						$name = $name . " ($label)";
					}
					$temperature = $lineArray[2];
					
					if(!in_array($date, $labelsArray)) {
						$labelsArray[] = $date;
					}
					
					if(!in_array($name, $seriesNamesArray)) {
						$seriesNamesArray[] = $name;
					}
					
					$dataArray[$name][$date] = addSeparatorToTemperature($temperature);
				}
			}
		}
	}
	
	foreach($labelsArray as $date) {
		$labelString .= "'$date'" . ', ';
		
		foreach($seriesNamesArray as $seriesName) {
			if(!array_key_exists($seriesName, $seriesStringsArray)) {
				$seriesStringsArray[$seriesName] = '';
			}
			
			if(array_key_exists($date, $dataArray[$seriesName])) {
				$seriesStringsArray[$seriesName] .=  $dataArray[$seriesName][$date] . ', ';
			} else {
				$seriesStringsArray[$seriesName] .= 'NaN, ';
			}
		}
	}
	
	ksort($seriesStringsArray);
	
	$colourArray = Array('red','blue','green','yellow');
	
	$seriesString = '';
	$counter = 0;
	foreach($seriesStringsArray as $key=>$value) {
		//TODO change colors
		$seriesString .= "{label: '$key', data: [$value], backgroundColor: color(window.chartColors.$colourArray[$counter]).alpha(0.2).rgbString(), borderColor: window.chartColors.$colourArray[$counter], fill: true,},";
		$counter++;
	}
	
	$chartString = "data: {labels: [$labelString], datasets: [$seriesString],},";
	return $chartString;
}

$dataDir = ROOT . 'data' . DS;
$filenameMinimum = $dataDir . 'minimum.csv';
$filenameMaximum = $dataDir . 'maximum.csv';
$filenameArray = Array($filenameMaximum=>'max', $filenameMinimum=>'min');

$chartString = returnChartistString($filenameArray);

?>

<!doctype html>
<html>

<head>
    <title>Legend Positions</title>
    <script src="js/Chart.bundle.min.js"></script>
    <script src="js/utils.js"></script>
    <style>
        canvas {
            -moz-user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
        }
        .chart-container {
            width: 500px;
            margin-left: 40px;
            margin-right: 40px;
        }
        .container {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
        }
    </style>
</head>

<body>
    <div style="width:75%;">
        <canvas id="canvas"></canvas>
    </div>
    <script>
        var color = Chart.helpers.color;
        var config = {
            type: 'line',
            <?php echo $chartString; ?>
            options: {
                responsive: true,
                title:{
                    display:true,
                    text:'Temperature'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Day'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: '°C'
                        },
                        ticks: {
                            beginAtZero:true
                        }
                    }]
                }
            }
        };

        window.onload = function() {
            var ctx = document.getElementById("canvas").getContext("2d");
            window.myLine = new Chart(ctx, config);
        };

    </script>
</body>

</html>
