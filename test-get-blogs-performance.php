<?php
/**
 * Performance Test for get_blogs_of_user()
 * 
 * This script demonstrates the performance issues with get_blogs_of_user()
 * and shows the improvement with caching.
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/src/wp-load.php' );

// Ensure we're in multisite
if ( ! is_multisite() ) {
    die( "This test requires multisite to be enabled.\n" );
}

echo "=== get_blogs_of_user() Performance Test ===\n\n";

// Create test users with different numbers of sites
$test_users = array();

// Create users with varying numbers of sites
for ( $i = 1; $i <= 5; $i++ ) {
    $user_id = wp_create_user( "testuser{$i}", "password123", "test{$i}@example.com" );
    if ( ! is_wp_error( $user_id ) ) {
        $test_users[] = $user_id;
        
        // Add user to multiple sites
        for ( $j = 1; $j <= $i * 2; $j++ ) {
            if ( $j <= 10 ) { // Limit to 10 sites max
                add_user_to_blog( $j, $user_id, 'subscriber' );
            }
        }
    }
}

echo "Created " . count( $test_users ) . " test users\n\n";

// Test 1: Single user performance
echo "Test 1: Single User Performance\n";
echo "--------------------------------\n";

$user_id = $test_users[0];
$iterations = 100;

// Clear cache before testing
wp_cache_flush();

$start_time = microtime( true );
$start_memory = memory_get_usage();

for ( $i = 0; $i < $iterations; $i++ ) {
    $blogs = get_blogs_of_user( $user_id, false );
}

$end_time = microtime( true );
$end_memory = memory_get_usage();

$total_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds
$memory_used = $end_memory - $start_memory;

echo "User ID: {$user_id}\n";
echo "Iterations: {$iterations}\n";
echo "Total time: " . number_format( $total_time, 2 ) . " ms\n";
echo "Average time per call: " . number_format( $total_time / $iterations, 2 ) . " ms\n";
echo "Memory used: " . number_format( $memory_used / 1024, 2 ) . " KB\n\n";

// Test 2: Multiple users performance
echo "Test 2: Multiple Users Performance\n";
echo "----------------------------------\n";

$iterations = 50;
$total_time = 0;
$total_memory = 0;

for ( $test = 0; $test < $iterations; $test++ ) {
    $start_time = microtime( true );
    $start_memory = memory_get_usage();
    
    foreach ( $test_users as $user_id ) {
        $blogs = get_blogs_of_user( $user_id, false );
    }
    
    $end_time = microtime( true );
    $end_memory = memory_get_usage();
    
    $total_time += ( $end_time - $start_time ) * 1000;
    $total_memory += $end_memory - $start_memory;
}

echo "Users tested: " . count( $test_users ) . "\n";
echo "Iterations: {$iterations}\n";
echo "Total time: " . number_format( $total_time, 2 ) . " ms\n";
echo "Average time per iteration: " . number_format( $total_time / $iterations, 2 ) . " ms\n";
echo "Average time per user: " . number_format( $total_time / ( $iterations * count( $test_users ) ), 2 ) . " ms\n";
echo "Total memory used: " . number_format( $total_memory / 1024, 2 ) . " KB\n\n";

// Test 3: Database query analysis
echo "Test 3: Database Query Analysis\n";
echo "-------------------------------\n";

// Enable query logging
global $wpdb;
$wpdb->queries = array();

$user_id = $test_users[0];
$blogs = get_blogs_of_user( $user_id, false );

echo "Queries executed for single user:\n";
foreach ( $wpdb->queries as $i => $query ) {
    echo sprintf( "%d. %s (%.4f ms)\n", $i + 1, $query[0], $query[1] * 1000 );
}

echo "\nTotal queries: " . count( $wpdb->queries ) . "\n";
echo "Total query time: " . number_format( array_sum( array_column( $wpdb->queries, 1 ) ) * 1000, 2 ) . " ms\n\n";

// Test 4: Cache effectiveness
echo "Test 4: Cache Effectiveness Test\n";
echo "--------------------------------\n";

// Clear cache
wp_cache_flush();

// First call (no cache)
$start_time = microtime( true );
$blogs1 = get_blogs_of_user( $user_id, false );
$first_call_time = ( microtime( true ) - $start_time ) * 1000;

// Second call (should use cache if implemented)
$start_time = microtime( true );
$blogs2 = get_blogs_of_user( $user_id, false );
$second_call_time = ( microtime( true ) - $start_time ) * 1000;

echo "First call (no cache): " . number_format( $first_call_time, 2 ) . " ms\n";
echo "Second call (with cache): " . number_format( $second_call_time, 2 ) . " ms\n";
echo "Cache effectiveness: " . number_format( ( ( $first_call_time - $second_call_time ) / $first_call_time ) * 100, 1 ) . "%\n\n";

// Test 5: Memory usage analysis
echo "Test 5: Memory Usage Analysis\n";
echo "-----------------------------\n";

$memory_before = memory_get_usage();
$blogs = get_blogs_of_user( $user_id, false );
$memory_after = memory_get_usage();

echo "Memory before: " . number_format( $memory_before / 1024, 2 ) . " KB\n";
echo "Memory after: " . number_format( $memory_after / 1024, 2 ) . " KB\n";
echo "Memory used: " . number_format( ( $memory_after - $memory_before ) / 1024, 2 ) . " KB\n\n";

// Cleanup
echo "Cleaning up test data...\n";
foreach ( $test_users as $user_id ) {
    wp_delete_user( $user_id );
}

echo "Test completed!\n"; 