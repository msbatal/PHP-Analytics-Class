<?php

    require_once ('SunDB.php');        // Call 'SunDB' class (dependency, see github.com/msbatal/PHP-PDO-Database-Class)
    require_once ('SunAnalytics.php'); // Call 'SunAnalytics' class

    // Import test.sql into your database first. SunAnalytics needs MySQL/MariaDB.
    // Don't forget to change dbname, username, and password below.
    $db = new SunDB(['driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'dbname' => 'test', 'username' => 'test', 'password' => '1234', 'charset' => 'utf8mb4']);

    // Looking for a ready-made dashboard (charts, tables, traffic sources)? See dashboard.php in this folder.

    // Initialize SunAnalytics
    $analytics = new SunAnalytics($db, [
        'salt'  => 'change-this-secret', // secret used to hash the visitor identity (set your own)
        'hosts' => ['localhost']         // your own host names (not counted as a traffic source)
    ]);


    // Example for Tracking (call it once per page view, before any output)
    $analytics->track(); // returns the visit id, or false when the request is not tracked (bot, CLI, excluded...)

    echo '<pre>';

    // Example for Summary (last 30 days)
    echo "Summary (30 days):\n";
    print_r ( $analytics->summary(30) ); // visits, pageviews, visitors, bounces, bounce_rate, pages_per_visit

    // Example for Traffic Sources (top 5, last 30 days)
    echo "\nTraffic Sources (30 days):\n";
    print_r ( $analytics->sources(30, 5) ); // name, medium, visits, pageviews, bounce_rate, share

    echo '</pre>';


    /*
    // Example for Daily Visits (last 7 days, days without visits are included)
    print_r ( $analytics->daily(7) ); // date => [visits, pageviews, visitors]
    */


    /*
    // Example for Breakdown by Dimension
    print_r ( $analytics->breakdown('medium', 30) );        // organic, social, email, cpc, referral, direct...
    print_r ( $analytics->breakdown('campaign', 30, 10) );  // utm_campaign values
    print_r ( $analytics->breakdown('referrer', 30, 10) );  // referrer hosts
    print_r ( $analytics->breakdown('landing', 30, 10) );   // landing pages
    print_r ( $analytics->breakdown('device', 30) );        // desktop, mobile, tablet
    print_r ( $analytics->breakdown('browser', 30) );
    print_r ( $analytics->breakdown('os', 30) );
    print_r ( $analytics->breakdown('language', 30) );
    */


    /*
    // Example for Most Viewed Pages
    print_r ( $analytics->pages(30, 10) ); // path, views, visits
    */


    /*
    // Example for Custom Periods
    print_r ( $analytics->summary('today') );
    print_r ( $analytics->summary('yesterday') );
    print_r ( $analytics->summary(['2026-10-01', '2026-10-31']) ); // [from, to]
    */


    /*
    // Example for Online Visitors (seen in the last 5 minutes)
    echo 'Online: '.$analytics->online();
    */


    /*
    // Example for Detecting a Traffic Source (without tracking)
    print_r ( $analytics->detectSource('https://www.google.com/', '/product?utm_campaign=autumn') );
    print_r ( $analytics->detectSource('', '/?utm_source=newsletter&utm_medium=email&utm_campaign=october') );
    print_r ( $analytics->detectSource('', '/?gclid=abc123') ); // paid click ids are recognized as google / cpc
    */


    /*
    // Example for Tracking from an Ajax Beacon (cached or static pages)
    $analytics->track([
        'referrer' => isset($_POST['ref'])   ? $_POST['ref']   : '', // document.referrer
        'url'      => isset($_POST['url'])   ? $_POST['url']   : '/', // location.pathname + location.search
        'path'     => isset($_POST['url'])   ? $_POST['url']   : '/',
        'title'    => isset($_POST['title']) ? $_POST['title'] : ''
    ]);
    */


    /*
    // Example for Device, Browser and Bot Detection
    print_r ( $analytics->parseAgent($_SERVER['HTTP_USER_AGENT']) ); // device, browser, os
    var_dump ( $analytics->isBot('Googlebot/2.1') );                  // true
    */


    /*
    // Example for Configuration (get/set a config value at runtime)
    echo 'Session timeout: '.$analytics->config('sessionTimeout').'<br>'; // get
    $analytics->config('sessionTimeout', 900);                            // set (15 minutes)
    $analytics->config('respectDnt', true);                               // skip visitors that send Do Not Track
    */


    /*
    // Example for Creating the Tables (test.sql already did it for this demo)
    $analytics->install(); // safe to run repeatedly
    */


    /*
    // Example for Cleaning Old Records (run it from a daily cron job)
    print_r ( $analytics->purge(180) ); // deletes visits and page views older than 180 days
    */


    /*
    // Example for Error Handling (track() never breaks the page)
    if ($analytics->track() === false) {
        echo 'Not tracked: '.$analytics->lastError(); // empty when the request was skipped on purpose (bot, CLI...)
    }
    */

?>
