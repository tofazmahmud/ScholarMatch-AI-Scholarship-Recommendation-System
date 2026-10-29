<?php
require_once __DIR__.'/v2.php';
function sm_v2_head(string $title): void { ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= sm_h($title) ?> | ScholarMatch V2</title>
<link rel="stylesheet" href="assets/css/student.css"><link rel="stylesheet" href="assets/css/v2.css"></head><body>
<header class="site-header"><div class="nav-container">
<a href="index.php" class="brand">✦ ScholarMatch</a>
<nav aria-label="Main navigation"><a href="index.php">Home</a><a href="dashboard.php">Dashboard</a><a href="explore.php">Explore</a><a href="semantic_search.php">AI Search</a><a href="bookmarks.php">Saved</a><a href="application_tracker.php">Tracker</a><a href="deadline_calendar.php">Deadlines</a><a href="profile.php">Profile</a><a href="recommendations.php">Matches</a></nav></div></header>
<?php }
function sm_v2_footer(): void { ?>
<footer class="site-footer"><p>ScholarMatch V2 · Evidence-led scholarship discovery · Check all details with the official provider.</p></footer></body></html>
<?php }
function sm_v2_begin(string $kicker,string $title,string $sub=''): void { ?>
<main class="v2-wrap"><div class="v2-page-heading"><span class="v2-kicker"><?= sm_h($kicker) ?></span><h1><?= sm_h($title) ?></h1><?php if($sub): ?><p><?= sm_h($sub) ?></p><?php endif; ?></div>
<?php }
