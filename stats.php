<?php

    /**
     * index.php
     *
     * Scan through all posts on a site and produce some basic stats on them.
     *
     * @author     Neil Thompson <neil@spokenlikeageek.com>
     * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU General Public License v3.0
     * @link       https://github.com/williamsdb/wordpress-post-stats GitHub Repository
     * @see        https://www.spokenlikeageek.com/2023/08/02/exporting-all-wordpress-posts-to-pdf/ Blog post
     *      *
     */

    // turn off reporting of notices
    error_reporting(E_ALL & ~E_NOTICE);
    
    // Your WordPress site URL
    $site_url = $_REQUEST['site'];

    // Your WordPress username and password or application password
    $username = $_REQUEST['username'];
    $password = $_REQUEST['password'];

    // API endpoint to retrieve posts
    $api_url = $site_url . '/wp-json/wp/v2/';

    // set pagination
    $page = 1;
    $postCount = 0;
    $finished = 0;
    $years = [];
    $months = [];
    $dow = [];
    $tags = [];
    $categories = [];

    while ($finished==0){

        // Initialize cURL session
        $ch = curl_init($api_url.'posts?_embed&order=asc&per_page=10&offset='.($page-1)*10);

        // Set cURL options for authentication
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);

        // Set cURL option to return the response instead of outputting it directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // if this is the first time through get the total number of posts to be processed
        if ($page == 1){
            // this function is called by curl for each header received
            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function($curl, $header) use (&$headers)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                return $len;
        
                $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                
                return $len;
            }
            );    
        }

        // Execute the cURL session and get the API response
        $response = curl_exec($ch);
        if ($page == 1){
            $totalPages = $headers['x-wp-total'][0];
            $cnt = $totalPages-1;
        }
        $posts = json_decode($response, true);

        // Check for errors
        $info = curl_getinfo($ch);        
        if ($info['http_code'] != '200') {
            die("Error retrieving posts ");
        }
        if (curl_errno($ch)) {
            die('Error: ' . curl_error($ch).PHP_EOL);
        }elseif(isset($posts['code'])){
            die($posts['message'].PHP_EOL);
        }

        // Close cURL session
        curl_close($ch);

        // Process the API response (in JSON format)
        if ($response) {
            // process this batch of posts
            $i=0;
            while ($i<count($posts)){
                $dateString = $posts[$i]['date']; // Example date in 'YYYY-MM-DD' format

                // Create a DateTime object from the string
                $date = new DateTime($dateString);
                
                // Extract the month name
                $monthName = $date->format('F'); // Full month name (e.g., January)
                
                // Extract the day of the week name
                $dayOfWeekName = $date->format('l'); // Full day name (e.g., Saturday)
                
                // Extract the year
                if (isset($years[substr($posts[$i]['date'],0,4)])){
                    $years[substr($posts[$i]['date'],0,4)]['count']++;
                }else{
                    $years[substr($posts[$i]['date'],0,4)]['count'] = 1;
                }

                // Extract the month
                if (isset($months[$monthName])){
                    $months[$monthName]++;
                }else{                  
                    $months[$monthName] = 1;
                }

                // Extract the day of the week
                if (isset($dow[$dayOfWeekName])){
                    $dow[$dayOfWeekName]++;
                }else{
                    $dow[$dayOfWeekName] = 1;
                }

                // grab any categories and tags
                $tax = $posts[$i]['_embedded']['wp:term'];
                $j = 0;
                while ($j<count($tax)){

                    $tmp = $posts[$i]['_embedded']['wp:term'][$j];

                    $k = 0;
                    while ($k<count($tmp)){

                        if ($posts[$i]['_embedded']['wp:term'][$j][$k]['taxonomy'] == 'category'){
                            if (isset($categories[$posts[$i]['_embedded']['wp:term'][$j][$k]['name']])){
                                $categories[$posts[$i]['_embedded']['wp:term'][$j][$k]['name']]++; 
                            }else{                 
                                $categories[$posts[$i]['_embedded']['wp:term'][$j][$k]['name']] = 1;
                            }
                        }elseif($posts[$i]['_embedded']['wp:term'][$j][$k]['taxonomy'] == 'post_tag'){
                            if (isset($tags[$posts[$i]['_embedded']['wp:term'][$j][$k]['name']])){
                                $tags[$posts[$i]['_embedded']['wp:term'][$j][$k]['name']]++;
                            }else{
                                $tags[$posts[$i]['_embedded']['wp:term'][$j][$k]['name']] = 1;
                            }
                        }
                        $k++;
                    }

                    $j++;
                }

                $postCount++;

                $i++;
            }
        } else {
            echo 'No response from the API.';
        }

        // check to see if we have processed all posts we want
        if ($postCount>$cnt){
            $finished = 1;
        }else{
            $page++;
        }

    }
    
    // Define the desired order for sorting the days of the week and months
    $dayOrder = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $monthOrder = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    // Custom comparison function for sorting days of the week
    uksort($dow, function($a, $b) use ($dayOrder) {
        return array_search($a, $dayOrder) - array_search($b, $dayOrder);
    });

    // Custom comparison function for sorting months
    uksort($months, function($a, $b) use ($monthOrder) {
        return array_search($a, $monthOrder) - array_search($b, $monthOrder);
    });

    // Sort the tags array by value (count) in descending order
    arsort($tags);

    // Extract the top 5 entries
    $top5Tags = array_slice($tags, 0, 5, true);

    // sort the categories array by value (count) in descending order
    arsort($categories);

    // Extract the top 5 entries
    $top5Categories = array_slice($categories, 0, 5, true);

    // Prepare data for the charts
    $labelsYears = array_keys($years); // Extracts the years as an array: [2002, 2005, 2006, ...]
    $countsYears = array_column($years, 'count'); // Extracts the counts as an array: [4, 7, 4, ...]
    $labelsMonths = array_keys($months); // Extracts the months as an array: [January, February, March, ...]
    $countsMonths = array_values($months); // Extracts the counts as an array: [4, 7, 4, ...]
    $labelsDow = array_keys($dow); // Extracts the days of the week as an array: [Monday, Tuesday, Wednesday, ...]
    $countsDow = array_values($dow); // Extracts the counts as an array: [4, 7, 4, ...]

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta charset="utf-8">

        <!-- Title and other stuffs -->
        <title>Wordpress Stats</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Simple script to display Wordpress statistics">
        <meta name="keywords" content="wordpress, stats, statistics, php, script">
        <meta name="author" content="Neil Thompson">

        <!-- Stylesheet -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@docsearch/css@3">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body>
        <main>
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1>Simple Wordpress Post Stats</h1>
                        <h3>Headline stats for <a href="<?php echo $site_url; ?>" target="_blank"><?php echo $site_url; ?></a></h3>
                        <p><strong>Total posts:</strong> <?php echo $postCount; ?> </p>
                        <p><strong>Most posts in a year:</strong> <?php echo max($countsYears); ?> in <?php echo $labelsYears[array_search(max($countsYears), $countsYears)]; ?></p>
                        <p><strong>Least posts in a year:</strong> <?php echo min($countsYears); ?> in <?php echo $labelsYears[array_search(min($countsYears), $countsYears)]; ?></p>
                        <p><strong>Month most likely to post:</strong> <?php echo $labelsMonths[array_search(max($countsMonths), $countsMonths)]; ?></p>
                        <p><strong>Day most likely to post on:</strong> <?php echo $labelsDow[array_search(max($countsDow), $countsDow)]; ?> </p>
                        <div class="container content-container">
                            <div class="row">
                                <!-- Column for Top 10 Tags -->
                                <div class="col-lg-5 col-md-6 column">
                                    <h2 class="text-center">Top 5 Tags</h3>
                                    <table class="table table-striped table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Tag</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top5Tags as $tag => $count): ?>
                                                <tr>
                                                    <td><a href="<?php echo $site_url; ?>/tag/<?= htmlspecialchars($tag); ?>" target="_blank"><?= htmlspecialchars($tag); ?></a></td>
                                                    <td><?= htmlspecialchars($count); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Spacer between columns -->
                                <div class="col-lg-1"></div>

                                <!-- Column for Top 10 Categories -->
                                <div class="col-lg-5 col-md-6 column">
                                    <h2 class="text-center">Top 5 Categories</h3>
                                    <table class="table table-striped table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Category</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top5Categories as $category => $count): ?>
                                                <tr>
                                                    <td><a href="<?php echo $site_url; ?>/category/<?= htmlspecialchars($category); ?>" target="_blank"><?= htmlspecialchars($category); ?></a></td>
                                                    <td><?= htmlspecialchars($count); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <h3>Posts by Year</h3>
                        <canvas id="barChartYears" width="800" height="400"></canvas>
                        <h3>Posts by Month</h3>
                        <canvas id="barChartMonths" width="800" height="400"></canvas>
                        <h3>Posts by Day of Week</h3>
                        <canvas id="barChartDow" width="800" height="400"></canvas>
                    </div>
                </div>
            </div>
        </main>
        <script>
            // Use PHP variables in JavaScript
            const years = <?php echo json_encode($labelsYears); ?>; // PHP $labels becomes JS years
            const countsY = <?php echo json_encode($countsYears); ?>; // PHP $counts becomes JS counts (array)
            const months = <?php echo json_encode($labelsMonths); ?>; // PHP $labels becomes JS months
            const countsM = <?php echo json_encode($countsMonths); ?>; // PHP $counts becomes JS counts (array)
            const dow = <?php echo json_encode($labelsDow); ?>; // PHP $labels becomes JS dow
            const countsD = <?php echo json_encode($countsDow); ?>; // PHP $counts becomes JS counts (array)

            // Create the Years bar chart
            const ctxy = document.getElementById('barChartYears').getContext('2d');
            new Chart(ctxy, {
                type: 'bar',
                data: {
                    labels: years, // Use years array for labels
                    datasets: [{
                        label: 'Count of posts by year',
                        data: countsY, // Use counts array for data
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Year'
                            }
                        }
                    }
                }
            });

            // Create the Months bar chart
            const ctxm = document.getElementById('barChartMonths').getContext('2d');
            new Chart(ctxm, {
                type: 'bar',
                data: {
                    labels: months, // Use months array for labels
                    datasets: [{
                        label: 'Count of posts by month',
                        data: countsM, // Use counts array for data
                        backgroundColor: 'rgba(153, 102, 255, 0.6)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });

            // Create the dow bar chart
            const ctxd = document.getElementById('barChartDow').getContext('2d');
            new Chart(ctxd, {
                type: 'bar',
                data: {
                    labels: dow, // Use dow array for labels
                    datasets: [{
                        label: 'Count of posts by day of week',
                        data: countsD, // Use counts array for data
                        backgroundColor: 'rgba(255, 159, 64, 0.6)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Day of Week'
                            }
                        }
                    }
                }
            });

        </script>
    </body>
</html>
