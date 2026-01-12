-- =====================================================
-- SQL Queries to Debug JetEngine CCT Field Storage
-- Run these in phpMyAdmin or your database tool
-- =====================================================

-- 1. Find all JetEngine CCT posts (they're stored as CPTs)
-- Replace 'wp_' with your actual table prefix if different
SELECT 
    ID,
    post_title as cct_name,
    post_name as cct_slug,
    post_type,
    post_status
FROM wp_posts 
WHERE post_type = 'jet-engine-cct'
ORDER BY post_title;

-- 2. Get the full data for "makes" CCT (ID should be 49 based on your log)
SELECT 
    ID,
    post_title,
    post_name,
    post_content,
    post_excerpt
FROM wp_posts 
WHERE ID = 49;

-- 3. Check post meta for the "makes" CCT (this is where fields are stored)
SELECT 
    meta_key,
    meta_value
FROM wp_postmeta 
WHERE post_id = 49;

-- 4. Get ALL meta for all JetEngine CCTs (to see the pattern)
SELECT 
    p.ID,
    p.post_title as cct_name,
    pm.meta_key,
    LEFT(pm.meta_value, 200) as meta_value_preview
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'jet-engine-cct'
ORDER BY p.ID, pm.meta_key;

-- 5. Look for the specific fields meta key (JetEngine usually uses '_cct_args' or similar)
SELECT 
    p.post_title as cct_name,
    pm.meta_key,
    pm.meta_value
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'jet-engine-cct'
  AND pm.meta_key LIKE '%field%'
  OR pm.meta_key LIKE '%args%'
  OR pm.meta_key LIKE '%meta%';

-- 6. Check if there's a separate JetEngine table (some versions use this)
SHOW TABLES LIKE '%jet_cct%';

-- 7. If you find a jet_cct table, query it:
-- SELECT * FROM wp_jet_cct WHERE slug = 'makes';

-- =====================================================
-- Expected Results:
-- =====================================================
-- For query #3 (post meta), you should see something like:
-- meta_key: _cct_args
-- meta_value: serialized array containing 'fields' => [your fields array]
--
-- If the 'fields' array is empty or doesn't exist, the update isn't persisting!
-- =====================================================

