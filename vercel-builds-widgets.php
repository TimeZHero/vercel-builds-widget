<?php

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Vercel Builds Widgets
 * 
 * @package     Vercel Builds Widgets
 * @author      Stefano Fasoli <stefanofasoli17@gmail.com>
 * @version     1.0.0
 * 
 * @wordpress-plugin
 * Plugin Name:     Vercel Builds Widgets
 * Description:     Display charts based on the vercel-builds plugin
 * Version:         1.0.0
 * Author:          Stefano Fasoli <stefanofasoli17@gmail.com>
 * Text Domain:     vercel-builds-widgets
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_dashboard_setup', function () {
    // Bail early if charts are not enabled
    if (! is_plugin_active('simple-charts/simple-charts.php')
    || ! is_plugin_active('vercel-builds/vercel-builds.php')) {
        return;
    }

    // Latest week builds
    $latestBuilds = new \WP_Query([
        'post_type'     => 'vercel_builds',
        'date_query'    => [
            // a week ago from 00:00:00
            'after' => date('j F o', strtotime('-1 week'))
        ]
    ]);
    
    $failed = collect($latestBuilds->posts)->filter(fn ($build) => get_post_meta($build->ID, 'status', true) === 'deployment.error');
    $percentile = $latestBuilds->post_count ? round(($failed->count()/$latestBuilds->post_count)*100, 2) : 0;
    
    wp_add_dashboard_widget('vercel-failed-builds', "Failed builds: {$percentile}%", function () use ($failed) {
        $failsByDate = $failed->countBy(fn ($build) => date('j/m', strtotime($build->post_date)));
        $data = collect();

        for ($i = 6; $i >= 0; $i--) {
            $key = Carbon::now()->subDays($i)->format('j/m');
            
            $data->put($key, $failsByDate->get($key, 0));
        }

        $labels = $data->keys()->implode(', ');
        $values = $data->values()->implode(', ');
        $type = 'bar';
        $label = 'Failed this day';
        $color = '#e60000';
        $options = "
            scales: {
                y: {
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Failed builds',
                    position: 'left'
                },
                legend: {
                    display: false
                }
            }
        ";
        
        echo do_shortcode("[simple_chart type=\"{$type}\" labels=\"{$labels}\" data=\"{$values}\" label=\"{$label}\" color=\"{$color}\" options=\"{$options}\"]");
    }, null, null, 'side');

    // Latest successful builds
    $successfulBuilds = new \WP_Query([
        'post_type'         => 'vercel_builds',
        'meta_key'          => 'status',
        'meta_value'        => 'deployment.succeeded',
        'orderby'           => 'date',
        'order'             => 'DESC',
        'posts_per_page'    => 7,
        'no_found_rows'     => true,
    ]);

    $successful = collect($successfulBuilds->posts);
    
    // Bail early if there is nothing to show
    if ($successful->isEmpty()) {
        return;
    }

    $timers = $successful->map(fn ($post) => get_post_meta($post->ID, 'end', true) - get_post_meta($post->ID, 'start', true));
    $timers = collect([51, 64, 72, 61, 71, 58]);
    $avg = round($timers->avg());

    wp_add_dashboard_widget('build-time', "Average build time: {$avg} seconds", function () use ($timers) {
        $labels = Str::repeat(',', $timers->count() - 1) ?: ',';
        $data = $timers->implode(', ');
        $type = 'line';
        $label = 'Build time in seconds';
        $color = '#328266';
        $options = "
            scales: {
                y: {
                    ticks: {
                        callback: function(label,index,labels) {
                            const totalSeconds = label;
                            const totalMinutes = Math.floor(totalSeconds / 60);
                            const seconds = totalSeconds % 60;
                            const hours = Math.floor(totalMinutes / 60);
                            const minutes = totalMinutes % 60;
                            var string = '';

                            string += hours ? hours+'h ' : '';
                            string += minutes ? minutes+'m ' : '';
                            string += seconds ? seconds+'s' : '';
                            
                            return string;
                        }
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Latest successful builds',
                    position: 'bottom'
                },
                legend: {
                    display: false
                }
            }
        ";
        
        echo do_shortcode("[simple_chart type=\"{$type}\" labels=\"{$labels}\" data=\"{$data}\" label=\"{$label}\" color=\"{$color}\" options=\"{$options}\"]");
    }, null, null, 'side');
});
