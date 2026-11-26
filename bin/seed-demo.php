<?php
declare(strict_types=1);
// php bin/seed-demo.php          add ~24 sample published articles (for trying out the home and explore pages)
// php bin/seed-demo.php --clear  remove the sample articles again
// Uses your existing active students and teachers as authors. Sample rows are marked review_note = 'demo-seed'.
require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Services\Articles;

if (in_array('--clear', $argv, true)) {
    $n = DB::exec("DELETE FROM articles WHERE review_note = 'demo-seed'");
    echo "[demo] removed $n sample articles\n";
    exit(0);
}

$authors = DB::all("SELECT u.id, p.department_id FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.role IN ('student','teacher') AND u.status = 'active' ORDER BY u.id LIMIT 8");
if (!$authors) { fwrite(STDERR, "[demo] no active students or teachers yet: create a few in the moderator portal first.\n"); exit(1); }
$types = DB::all('SELECT id FROM article_types WHERE active = 1');

$en = [
  ['How a small campus lab is rethinking solar cooling', 'Researchers in the engineering faculty built a low-cost cooling unit that runs on direct sunlight.'],
  ['Why every student should learn to read a balance sheet', 'Money literacy is rarely taught outside business school, and it shows.'],
  ['Notes from the monsoon: a field study on river erosion', 'Three weeks along the Surma with a notebook, a drone and a lot of mud.'],
  ['A beginner\'s guide to version control', 'Commits, branches and the habits that save your thesis at 2 a.m.'],
  ['The quiet revival of the university library', 'Quiet floors, group rooms and a surprising rise in borrowed books.'],
  ['In conversation: a professor on forty years of teaching physics', 'On chalk, curiosity and why the best question is often "why not?"'],
  ['Reading "The Shadow Lines" again after ten years', 'A review that starts as a memory and ends as an argument.'],
  ['Open data and the future of campus transport', 'What bus timing logs can tell us about a city that never stops moving.'],
];
$bn = [
  ['ক্যাম্পাসে বৃষ্টির দিনের গল্প', 'প্রথম বৃষ্টির দিনে ক্যাম্পাসের চেহারাই বদলে যায়, সেই ছোট ছোট দৃশ্যের কথা।'],
  ['বাংলা ভাষায় প্রযুক্তির শব্দ কীভাবে তৈরি হয়', 'নতুন শব্দ কোথা থেকে আসে আর কীভাবে আমাদের মুখে মুখে ছড়িয়ে পড়ে।'],
  ['গবেষণার প্রথম বছর: কী শিখলাম', 'ভুল দিয়ে শুরু, ধৈর্য দিয়ে শেষ—একজন স্নাতকোত্তর শিক্ষার্থীর অভিজ্ঞতা।'],
  ['হাওরের মানুষ ও জলবায়ু', 'ভাটি অঞ্চলের কৃষকেরা বদলে যাওয়া আবহাওয়ার সঙ্গে কীভাবে মানিয়ে নিচ্ছেন।'],
];
$ar = [['الكتابة الأكاديمية: خمس عادات صغيرة', 'عادات يومية بسيطة تحسّن جودة الورقة البحثية وتوفّر الوقت.']];

$para_en = ['The idea began as a simple question in a seminar room, and it refused to go away. Over the following months the team collected data, argued about method, and slowly found a shape for the work.',
  'What stands out is not a single result but the habit of checking each claim twice. Small, patient steps added up faster than anyone expected.',
  'There are limits, of course. The sample was small, the season was unusual, and several measurements had to be repeated. Still, the direction is clear enough to be worth sharing.',
  'If you want to try this yourself, start small, keep notes, and ask for feedback early. Most of what we learned came from people who disagreed with us politely.'];
$para_bn = ['শুরুটা ছিল ক্লাসরুমের একটি সাধারণ প্রশ্ন দিয়ে, কিন্তু প্রশ্নটা আমাদের পিছু ছাড়েনি। কয়েক মাস ধরে তথ্য সংগ্রহ, তর্ক আর পুনরায় যাচাইয়ের মধ্য দিয়ে কাজটা ধীরে ধীরে রূপ পেয়েছে।',
  'সবচেয়ে গুরুত্বপূর্ণ বিষয় কোনো একটি ফল নয়, বরং প্রতিটি দাবি দুবার যাচাই করার অভ্যাস। ছোট ছোট ধৈর্যশীল পদক্ষেপই শেষ পর্যন্ত বড় পার্থক্য গড়ে দিয়েছে।',
  'অবশ্যই সীমাবদ্ধতা আছে। নমুনা ছোট ছিল, আবহাওয়া ছিল অস্বাভাবিক। তবু দিকটা স্পষ্ট, তাই সবার সঙ্গে ভাগ করে নেওয়া দরকার।'];
$para_ar = ['بدأت الفكرة بسؤال بسيط في قاعة الندوة، ثم تحوّلت مع الوقت إلى مشروع متكامل. جمع الفريق البيانات وناقش المنهج مرارًا حتى استقر العمل على صورته الحالية.',
  'ما يلفت الانتباه ليس نتيجة واحدة، بل عادة مراجعة كل ادعاء مرتين. الخطوات الصغيرة والصبورة تتراكم أسرع مما يتوقع المرء.'];

$rows = [];
foreach ($en as $r) $rows[] = [$r[0], $r[1], 'en', $para_en];
foreach ($bn as $r) $rows[] = [$r[0], $r[1], 'bn', $para_bn];
foreach ($ar as $r) $rows[] = [$r[0], $r[1], 'ar', $para_ar];
foreach ($en as $r) $rows[] = ['Follow-up: ' . $r[0], $r[1], 'en', $para_en];   // a second batch, to fill the pages
$rows = array_slice($rows, 0, 24);

$n = 0;
foreach ($rows as $i => [$title, $summary, $lang, $paras]) {
    $au = $authors[$i % count($authors)];
    $body = "## " . ($lang === 'bn' ? 'ভূমিকা' : ($lang === 'ar' ? 'مقدمة' : 'Introduction')) . "\n\n" . implode("\n\n", $paras) . "\n\n> " . $paras[1] . "\n\n" . implode("\n\n", array_reverse($paras)) . "\n";
    $views = random_int(20, 4200);
    $likes = (int) ($views * random_int(2, 12) / 100);
    $dislikes = (int) ($likes * random_int(0, 12) / 100);
    $days = random_int(0, 60);
    DB::exec(
        "INSERT INTO articles (author_id, type_id, department_id, lang, title, slug, summary, body, status, review_label, review_note, is_featured,
                               like_count, dislike_count, comment_count, view_count, reading_minutes, submitted_at, published_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, 'demo-seed', ?, ?, ?, 0, ?, ?, NOW(), NOW() - INTERVAL ? DAY - INTERVAL ? MINUTE)",
        [(int) $au['id'], (int) $types[array_rand($types)]['id'], $au['department_id'], $lang, $title, Articles::slugify($title), $summary, $body,
         ['none', 'editor_reviewed', 'peer_reviewed'][random_int(0, 2)], $i === 1 ? 1 : 0, $likes, $dislikes, $views, Articles::readingMinutes($body) + random_int(2, 8), $days, random_int(0, 600)]
    );
    $n++;
}
echo "[demo] added $n sample articles (remove with: php bin/seed-demo.php --clear)\n";
