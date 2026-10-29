<?php
// mentee/search.php - AI-Powered Search
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentee();
requireActiveSubscription();

$user = getCurrentUser();
$error = '';
$search_results = [];
$search_term = '';
$search_type = 'all';
$sort_by = 'relevance';
$min_rating = 0;
$max_rate = 500;
$ai_suggestions = [];
$ai_understood = '';

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

// Get filter parameters
$search_term = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$search_type = isset($_GET['search_type']) ? sanitize($_GET['search_type']) : 'all';
$search_types = ['all', 'course', 'mentor', 'programme', 'topic'];
if (!in_array($search_type, $search_types, true)) {
    $search_type = 'all';
}
if ($search_term === '' && isset($_GET['course'])) {
    $search_term = sanitize($_GET['course']);
    $search_type = 'course';
}
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'relevance';
$min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;
$max_rate = isset($_GET['max_rate']) ? (float)$_GET['max_rate'] : 500;

// Get all courses for suggestions
$stmt = $pdo->prepare("
    SELECT DISTINCT ma.course_code,
           COALESCE(NULLIF(c.course_name, ''), NULLIF(ma.course_name, ''), ma.course_code) AS course_name
    FROM mentor_applications ma
    JOIN users u ON u.id = ma.mentor_id AND u.role = 'mentor' AND u.status = 'active'
    JOIN mentor_rates r ON r.mentor_id = ma.mentor_id AND r.course_code = ma.course_code
    LEFT JOIN courses c ON c.course_code = ma.course_code
    WHERE ma.status = 'approved' AND ma.semester_id = ?
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = ma.mentor_id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
    ORDER BY ma.course_code ASC
");
$stmt->execute([$active_semester['id'] ?? 0]);
$all_courses = $stmt->fetchAll();

// Get all mentors for AI search
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        u.id, 
        u.name, 
        u.email, 
        u.programme,
        u.year_of_study,
        r.course_code,
        COALESCE(NULLIF(c.course_name, ''), NULLIF(ma.course_name, ''), r.course_code) AS course_name,
        r.rate_per_hour,
        (SELECT AVG(rating) FROM feedback f WHERE f.mentor_id = u.id) AS avg_rating,
        (SELECT COUNT(*) FROM feedback f WHERE f.mentor_id = u.id) AS total_reviews,
        (SELECT COUNT(*) FROM sessions s WHERE s.mentor_id = u.id AND s.status = 'completed') AS completed_sessions
    FROM users u
    JOIN mentor_rates r ON u.id = r.mentor_id
    JOIN mentor_applications ma ON u.id = ma.mentor_id AND ma.course_code = r.course_code
    LEFT JOIN courses c ON c.course_code = r.course_code
    WHERE u.role = 'mentor' 
      AND u.status = 'active'
      AND ma.status = 'approved'
      AND ma.semester_id = ?
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
");
$stmt->execute([$active_semester['id'] ?? 0]);
$all_mentors = $stmt->fetchAll();

// ============================================
// AI SEARCH ENGINE
// ============================================
class AISearchEngine {
    private $mentors;
    private $courses;
    private $keywords;
    
    public function __construct($mentors, $courses) {
        $this->mentors = $mentors;
        $this->courses = $courses;
        $this->keywords = [
            'math' => ['mathematics', 'calculus', 'algebra', 'trigonometry', 'stats', 'statistics', 'mat', 'maths'],
            'programming' => ['coding', 'python', 'java', 'c++', 'javascript', 'web', 'software', 'programmer', 'developer', 'csc'],
            'science' => ['biology', 'chemistry', 'physics', 'bio', 'chem', 'phy', 'science', 'laboratory'],
            'business' => ['accounting', 'finance', 'marketing', 'economics', 'management', 'business', 'acc', 'fin', 'mkt', 'econ'],
            'english' => ['writing', 'literature', 'composition', 'grammar', 'essay', 'eng', 'english'],
            'psychology' => ['psych', 'mental', 'counseling', 'behavior', 'psy'],
            'engineering' => ['mechanical', 'electrical', 'civil', 'chemical', 'engineer', 'eng'],
            'law' => ['legal', 'constitution', 'criminal', 'contract', 'law', 'llb'],
            'nursing' => ['nurse', 'health', 'medical', 'care', 'nursing'],
            'accounting' => ['account', 'financial', 'audit', 'tax', 'acc'],
            'economics' => ['micro', 'macro', 'economy', 'trade', 'econ']
        ];
    }
    
    // Natural language processing
    public function processQuery($query, $searchType = 'all') {
        $query = strtolower(trim($query));
        $results = [];
        $intent = $this->detectIntent($query);
        $entities = $this->extractEntities($query);
        
        if ($searchType === 'course') {
            $intent = ['type' => 'course', 'value' => $query];
            $results = $this->searchByCourse($intent['value'], $entities);
        } elseif ($searchType === 'mentor') {
            $intent = ['type' => 'mentor', 'value' => $query];
            $results = $this->searchByMentor($intent['value'], $entities);
        } elseif ($searchType === 'topic') {
            $intent = ['type' => 'topic', 'value' => $query];
            $results = $this->searchByTopic($intent['value'], $entities);
        } elseif ($searchType === 'programme') {
            $intent = ['type' => 'programme', 'value' => $query];
            $results = $this->searchByProgramme($query, $entities);
        } elseif ($intent['type'] === 'course') {
            $results = $this->searchByCourse($intent['value'], $entities);
        } elseif ($intent['type'] === 'mentor') {
            $results = $this->searchByMentor($intent['value'], $entities);
        } elseif ($intent['type'] === 'topic') {
            $results = $this->searchByTopic($intent['value'], $entities);
        } else {
            $results = $this->searchGeneral($query, $entities);
        }
        
        return [
            'results' => $results,
            'intent' => $intent,
            'entities' => $entities,
            'query' => $query
        ];
    }
    
    // Detect search intent
    private function detectIntent($query) {
        $intent = ['type' => 'general', 'value' => $query];
        
        // Check for course code pattern (e.g., CSC101, MAT201)
        if (preg_match('/\b([A-Z]{2,4}\d{3,4})\b/i', $query, $matches)) {
            $intent = ['type' => 'course', 'value' => strtoupper($matches[1])];
            return $intent;
        }
        
        // Check for "help with" or "need help" pattern
        if (preg_match('/(?:help with|need help|assist with|tutoring in|mentor for)\s+([a-zA-Z\s]+)/i', $query, $matches)) {
            $intent = ['type' => 'topic', 'value' => trim($matches[1])];
            return $intent;
        }
        
        // Check for "looking for" pattern
        if (preg_match('/(?:looking for|find|search for|want)\s+([a-zA-Z\s]+)\s*(?:mentor|tutor)?/i', $query, $matches)) {
            $intent = ['type' => 'mentor', 'value' => trim($matches[1])];
            return $intent;
        }
        
        // Check if query contains a name pattern (mentor name search)
        if (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\b/', $query, $matches) && strlen($matches[1]) > 2) {
            $intent = ['type' => 'mentor', 'value' => $matches[1]];
            return $intent;
        }
        
        return $intent;
    }
    
    // Extract entities from query
    private function extractEntities($query) {
        $entities = [
            'rating' => null,
            'price' => null,
            'availability' => null,
            'level' => null,
            'programme' => null
        ];
        
        // Extract rating (e.g., "4 star", "highly rated", "best")
        if (preg_match('/(\d+)\s*star/i', $query, $matches)) {
            $entities['rating'] = (int)$matches[1];
        }
        if (preg_match('/highly\s*rated|top\s*rated|best\s*rated/i', $query)) {
            $entities['rating'] = 4.5;
        }
        
        // Extract price (e.g., "cheap", "affordable", "expensive", "under K50")
        if (preg_match('/(?:under|less than|below|max)\s*K?(\d+)/i', $query, $matches)) {
            $entities['price'] = (float)$matches[1];
        }
        if (preg_match('/cheap|affordable|low\s*cost|budget/i', $query)) {
            $entities['price'] = 50;
        }
        if (preg_match('/expensive|high\s*end|premium/i', $query)) {
            $entities['price'] = 150;
        }
        
        // Extract level (e.g., "beginner", "intermediate", "advanced")
        if (preg_match('/beginner|intro|basic|101/i', $query)) {
            $entities['level'] = 'beginner';
        }
        if (preg_match('/intermediate|medium|200/i', $query)) {
            $entities['level'] = 'intermediate';
        }
        if (preg_match('/advanced|expert|300|400|graduate/i', $query)) {
            $entities['level'] = 'advanced';
        }
        
        // Extract programme
        $programmes = ['computer science', 'information technology', 'software engineering', 'business', 'economics', 
                       'mathematics', 'physics', 'chemistry', 'biology', 'psychology', 'english', 'sociology', 
                       'engineering', 'accounting', 'finance', 'marketing', 'nursing', 'law'];
        foreach ($programmes as $prog) {
            if (stripos($query, $prog) !== false) {
                $entities['programme'] = $prog;
                break;
            }
        }
        
        return $entities;
    }
    
    // Search by course code
    private function searchByCourse($course, $entities) {
        $results = [];
        foreach ($this->mentors as $mentor) {
            $courseCode = strtolower($mentor['course_code']);
            $courseName = strtolower($mentor['course_name'] ?? '');
            if (strpos($courseCode, $course) !== false || strpos($courseName, $course) !== false) {
                $score = $this->calculateScore($mentor, $entities);
                if ($courseCode === $course || $courseName === $course) {
                    $score += 20;
                }
                $mentor['score'] = $score;
                $results[] = $mentor;
                continue;
            }

            $words = array_filter(preg_split('/\s+/', $course), function ($word) {
                return strlen($word) > 1;
            });
            if (count($words) > 1) {
                $courseText = $courseCode . ' ' . $courseName;
                $allWordsMatch = true;
                foreach ($words as $word) {
                    if (strpos($courseText, $word) === false) {
                        $allWordsMatch = false;
                        break;
                    }
                }
                if ($allWordsMatch) {
                    $mentor['score'] = $this->calculateScore($mentor, $entities) + 10;
                    $results[] = $mentor;
                }
            }
        }
        return $this->sortResults($results);
    }

    private function searchByProgramme($programme, $entities) {
        $results = [];
        foreach ($this->mentors as $mentor) {
            if (stripos($mentor['programme'] ?? '', $programme) !== false) {
                $mentor['score'] = $this->calculateScore($mentor, $entities) + 10;
                $results[] = $mentor;
            }
        }
        return $this->sortResults($results);
    }
    
    // Search by mentor name
    private function searchByMentor($name, $entities) {
        $results = [];
        $name_parts = explode(' ', strtolower($name));
        foreach ($this->mentors as $mentor) {
            $mentor_name = strtolower($mentor['name']);
            foreach ($name_parts as $part) {
                if (strpos($mentor_name, $part) !== false) {
                    $score = $this->calculateScore($mentor, $entities);
                    $mentor['score'] = $score + 10; // Boost for name match
                    $results[] = $mentor;
                    break;
                }
            }
        }
        return $this->sortResults($results);
    }
    
    // Search by topic/subject
    private function searchByTopic($topic, $entities) {
        $results = [];
        $topic = strtolower(trim($topic));
        $topic_words = explode(' ', $topic);
        
        foreach ($this->mentors as $mentor) {
            $match_found = false;
            $score = 0;
            
            // Check if topic matches any keyword
            foreach ($this->keywords as $key => $synonyms) {
                foreach ($synonyms as $syn) {
                    if (stripos($topic, $syn) !== false || stripos($mentor['course_code'], $syn) !== false || stripos($mentor['course_name'] ?? '', $syn) !== false) {
                        $match_found = true;
                        $score += 15;
                        break 2;
                    }
                }
            }
            
            // Check if topic matches course code directly
            if (stripos($mentor['course_code'], $topic) !== false) {
                $match_found = true;
                $score += 20;
            }

            if (stripos($mentor['course_name'] ?? '', $topic) !== false) {
                $match_found = true;
                $score += 20;
            }
            
            // Check if topic matches programme
            if ($mentor['programme'] && stripos($mentor['programme'], $topic) !== false) {
                $match_found = true;
                $score += 10;
            }
            
            if ($match_found) {
                $score += $this->calculateScore($mentor, $entities);
                $mentor['score'] = $score;
                $results[] = $mentor;
            }
        }
        
        // If no results, try fuzzy matching
        if (empty($results)) {
            foreach ($this->mentors as $mentor) {
                $similarity = 0;
                $course = strtolower($mentor['course_code']);
                $course_name = strtolower($mentor['course_name'] ?? '');
                $programme = strtolower($mentor['programme'] ?? '');
                
                foreach ($topic_words as $word) {
                    if (strlen($word) > 2) {
                        if (stripos($course, $word) !== false) {
                            $similarity += 5;
                        }
                        if (stripos($course_name, $word) !== false) {
                            $similarity += 5;
                        }
                        if (stripos($programme, $word) !== false) {
                            $similarity += 3;
                        }
                        // Levenshtein-like simple matching
                        if (similar_text($word, $course, $percent) > 60) {
                            $similarity += 2;
                        }
                    }
                }
                
                if ($similarity > 0) {
                    $score = $this->calculateScore($mentor, $entities) + $similarity;
                    $mentor['score'] = $score;
                    $results[] = $mentor;
                }
            }
        }
        
        return $this->sortResults($results);
    }
    
    // General search
    private function searchGeneral($query, $entities) {
        $results = [];
        $query = strtolower($query);
        $query_words = explode(' ', $query);
        
        foreach ($this->mentors as $mentor) {
            $score = 0;
            $match_found = false;
            
            // Check all fields
            $fields = [
                $mentor['name'],
                $mentor['course_code'],
                $mentor['course_name'] ?? '',
                $mentor['programme'] ?? '',
                $mentor['email']
            ];
            
            foreach ($fields as $field) {
                $field = strtolower($field);
                if (stripos($field, $query) !== false) {
                    $score += 20;
                    $match_found = true;
                }
                
                foreach ($query_words as $word) {
                    if (strlen($word) > 2 && stripos($field, $word) !== false) {
                        $score += 5;
                        $match_found = true;
                    }
                }
            }
            
            if ($match_found) {
                $score += $this->calculateScore($mentor, $entities);
                $mentor['score'] = $score;
                $results[] = $mentor;
            }
        }
        
        return $this->sortResults($results);
    }
    
    // Calculate score based on filters
    private function calculateScore($mentor, $entities) {
        $score = 0;
        
        // Rating bonus
        if ($entities['rating'] && $mentor['avg_rating']) {
            if ($mentor['avg_rating'] >= $entities['rating']) {
                $score += ($mentor['avg_rating'] * 2);
            }
        } elseif ($mentor['avg_rating']) {
            $score += $mentor['avg_rating'] * 1.5;
        }
        
        // Price filter
        if ($entities['price']) {
            if ($mentor['rate_per_hour'] <= $entities['price']) {
                $score += 10;
            }
        }
        
        // Programme match
        if ($entities['programme'] && $mentor['programme']) {
            if (stripos($mentor['programme'], $entities['programme']) !== false) {
                $score += 15;
            }
        }
        
        return $score;
    }
    
    // Sort results by score
    private function sortResults($results) {
        usort($results, function($a, $b) {
            return ($b['score'] ?? 0) - ($a['score'] ?? 0);
        });
        return $results;
    }
    
    // Get AI suggestions based on query
    public function getSuggestions($query) {
        $suggestions = [];
        $query = strtolower(trim($query));
        
        if (empty($query)) {
            return $suggestions;
        }
        
        // Course suggestions
        foreach ($this->courses as $course) {
            if (stripos($course['course_code'], $query) !== false || 
                stripos($course['course_name'], $query) !== false) {
                $suggestions[] = [
                    'type' => 'course',
                    'text' => $course['course_code'] . ' - ' . $course['course_name'],
                    'value' => $course['course_code']
                ];
            }
        }
        
        // Mentor suggestions
        foreach ($this->mentors as $mentor) {
            if (stripos($mentor['name'], $query) !== false) {
                $suggestions[] = [
                    'type' => 'mentor',
                    'text' => $mentor['name'] . ' (' . $mentor['course_code'] . ')',
                    'value' => $mentor['name']
                ];
            }
        }
        
        // Subject suggestions based on keywords
        foreach ($this->keywords as $key => $synonyms) {
            foreach ($synonyms as $syn) {
                if (stripos($query, $syn) !== false) {
                    $suggestions[] = [
                        'type' => 'subject',
                        'text' => ucfirst($key) . ' Mentors',
                        'value' => $key
                    ];
                    break;
                }
            }
        }
        
        // Limit suggestions
        return array_slice($suggestions, 0, 8);
    }
    
    // Get AI understanding of the query
    public function understandQuery($query) {
        $intent = $this->detectIntent($query);
        $entities = $this->extractEntities($query);
        
        $understandings = [];
        
        if ($intent['type'] === 'course') {
            $understandings[] = "I'll find mentors who teach <strong>" . htmlspecialchars($intent['value']) . "</strong>";
        } elseif ($intent['type'] === 'mentor') {
            $understandings[] = "I'll search for mentors named <strong>" . htmlspecialchars($intent['value']) . "</strong>";
        } elseif ($intent['type'] === 'topic') {
            $understandings[] = "I'll find mentors who specialize in <strong>" . htmlspecialchars($intent['value']) . "</strong>";
        } else {
            $understandings[] = "I'm searching for <strong>" . htmlspecialchars($query) . "</strong>";
        }
        
        if ($entities['rating']) {
            $understandings[] = "With minimum rating of <strong>" . $entities['rating'] . " ★</strong>";
        }
        
        if ($entities['price']) {
            $understandings[] = "With rates under <strong>K" . $entities['price'] . "</strong>";
        }
        
        if ($entities['programme']) {
            $understandings[] = "In <strong>" . htmlspecialchars($entities['programme']) . "</strong> programme";
        }
        
        return implode(' ', $understandings);
    }
}

// ============================================
// PERFORM AI SEARCH
// ============================================

$ai_search = new AISearchEngine($all_mentors, $all_courses);
$search_results = [];
$ai_understood = '';
$ai_suggestions = [];

if (!empty($search_term)) {
    $ai_response = $ai_search->processQuery($search_term, $search_type);
    $search_results = $ai_response['results'];
    $ai_understood = $ai_search->understandQuery($search_term);
    $ai_suggestions = $ai_search->getSuggestions($search_term);
    
    // Apply manual filters
    if ($min_rating > 0) {
        $search_results = array_filter($search_results, function($m) use ($min_rating) {
            return ($m['avg_rating'] ?? 0) >= $min_rating;
        });
    }
    
    if ($max_rate < 500) {
        $search_results = array_filter($search_results, function($m) use ($max_rate) {
            return $m['rate_per_hour'] <= $max_rate;
        });
    }
    
    // Sort by selected sort option
    if ($sort_by === 'rating') {
        usort($search_results, function($a, $b) {
            return ($b['avg_rating'] ?? 0) - ($a['avg_rating'] ?? 0);
        });
    } elseif ($sort_by === 'rate_low') {
        usort($search_results, function($a, $b) {
            return $a['rate_per_hour'] - $b['rate_per_hour'];
        });
    } elseif ($sort_by === 'rate_high') {
        usort($search_results, function($a, $b) {
            return $b['rate_per_hour'] - $a['rate_per_hour'];
        });
    } elseif ($sort_by === 'relevance') {
        // Already sorted by AI score
        usort($search_results, function($a, $b) {
            return ($b['score'] ?? 0) - ($a['score'] ?? 0);
        });
    }
    
    // Re-index array after filters
    $search_results = array_values($search_results);
}

$search_count = count($search_results);
$availability_by_mentor = [];
if ($search_results) {
    $mentor_ids = array_values(array_unique(array_map('intval', array_column($search_results, 'id'))));
    if ($mentor_ids) {
        $placeholders = implode(',', array_fill(0, count($mentor_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT mentor_id, day_of_week, start_time, end_time
            FROM mentor_availability
            WHERE is_recurring = 1 AND mentor_id IN ($placeholders)
            ORDER BY day_of_week, start_time
        ");
        $stmt->execute($mentor_ids);
        foreach ($stmt->fetchAll() as $availability_slot) {
            $availability_by_mentor[$availability_slot['mentor_id']][] = $availability_slot;
        }
    }
}
$weekday_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get top rated mentors
$stmt = $pdo->prepare("
    SELECT u.id, u.name, 
           (SELECT AVG(rating) FROM feedback f WHERE f.mentor_id = u.id) as avg_rating,
           (SELECT COUNT(*) FROM feedback f WHERE f.mentor_id = u.id) as total_reviews
    FROM users u
    WHERE u.role = 'mentor'
      AND u.status = 'active'
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
      AND (SELECT AVG(rating) FROM feedback f WHERE f.mentor_id = u.id) IS NOT NULL
    ORDER BY avg_rating DESC, total_reviews DESC
    LIMIT 5
");
$stmt->execute();
$top_mentors = $stmt->fetchAll();

// Get popular courses
$stmt = $pdo->prepare("
    SELECT ma.course_code,
           COALESCE(NULLIF(c.course_name, ''), NULLIF(ma.course_name, ''), ma.course_code) AS course_name,
           COUNT(DISTINCT ma.mentor_id) AS mentor_count
    FROM mentor_applications ma
    JOIN users u ON u.id = ma.mentor_id AND u.role = 'mentor' AND u.status = 'active'
    JOIN mentor_rates r ON r.mentor_id = ma.mentor_id AND r.course_code = ma.course_code
    LEFT JOIN courses c ON c.course_code = ma.course_code
    WHERE ma.status = 'approved' AND ma.semester_id = ?
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = ma.mentor_id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
    GROUP BY ma.course_code, course_name
    ORDER BY mentor_count DESC
    LIMIT 10
");
$stmt->execute([$active_semester['id'] ?? 0]);
$popular_courses = $stmt->fetchAll();

// Get total mentor count
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    JOIN mentor_applications ma ON u.id = ma.mentor_id
    WHERE u.role = 'mentor' AND u.status = 'active' AND ma.status = 'approved' AND ma.semester_id = ?
      AND EXISTS (
          SELECT 1 FROM subscriptions sub
          WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
      )
");
$stmt->execute([$active_semester['id'] ?? 0]);
$total_mentors = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Powered Mentor Search - MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .search-header {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            padding: 30px 20px;
            border-radius: 16px;
            color: white;
            margin-bottom: 25px;
        }
        .search-header h3 {
            margin-bottom: 5px;
        }
        .search-header p {
            opacity: 0.8;
            margin-bottom: 15px;
        }
        .search-box {
            position: relative;
        }
        .search-box .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 2;
        }
        .search-box input {
            padding-left: 45px;
            height: 55px;
            border-radius: 12px;
            border: none;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .search-box input:focus {
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .search-box .btn-search {
            position: absolute;
            right: 5px;
            top: 5px;
            bottom: 5px;
            border-radius: 10px;
            padding: 0 25px;
        }
        .ai-understanding {
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 10px 16px;
            margin-top: 12px;
            backdrop-filter: blur(10px);
        }
        .ai-understanding .ai-icon {
            font-size: 1.2rem;
            margin-right: 8px;
        }
        .ai-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .ai-suggestions.show {
            display: block;
        }
        .ai-suggestions .suggestion-item {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.2s ease;
            border-bottom: 1px solid #f8f9fa;
        }
        .ai-suggestions .suggestion-item:hover {
            background: #e7f1ff;
        }
        .ai-suggestions .suggestion-item .badge {
            font-size: 0.6rem;
            padding: 2px 8px;
        }
        .ai-suggestions .suggestion-item .suggestion-text {
            font-weight: 500;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .filter-section .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .mentor-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            overflow: hidden;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            position: relative;
        }
        .mentor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .mentor-card .relevance-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(13, 110, 253, 0.9);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .mentor-card .card-body {
            padding: 20px;
        }
        .mentor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .mentor-avatar.bg-1 { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .mentor-avatar.bg-2 { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .mentor-avatar.bg-3 { background: linear-gradient(135deg, #dc3545, #b02a37); }
        .mentor-avatar.bg-4 { background: linear-gradient(135deg, #ffc107, #d39e00); }
        .mentor-avatar.bg-5 { background: linear-gradient(135deg, #17a2b8, #0d6efd); }
        .mentor-avatar.bg-6 { background: linear-gradient(135deg, #6f42c1, #4a2a8a); }
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        .mentor-tag {
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 20px;
            background: #e9ecef;
            color: #495057;
            display: inline-block;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        .mentor-tag.course-tag {
            background: #e7f1ff;
            color: #0d6efd;
        }
        .stat-badge {
            text-align: center;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-badge .number {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .stat-badge .label {
            font-size: 0.7rem;
            color: #6c757d;
        }
        .ai-score-indicator {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            background: #e7f1ff;
            color: #0d6efd;
        }
        .no-results {
            text-align: center;
            padding: 40px 20px;
        }
        .no-results .icon {
            font-size: 4rem;
            color: #dee2e6;
        }
        .popular-course {
            display: inline-block;
            padding: 6px 14px;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #495057;
            text-decoration: none;
            margin: 4px;
            transition: all 0.3s ease;
        }
        .popular-course:hover {
            background: #0d6efd;
            color: white;
        }
        .search-type-badge {
            cursor: pointer;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid transparent;
        }
        .search-type-badge.active {
            background: #e7f1ff;
            color: #0d6efd;
            border-color: #0d6efd;
        }
        .search-type-badge:hover {
            background: #e9ecef;
        }
        .ai-chip {
            background: #e7f1ff;
            color: #0d6efd;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        @media (max-width: 768px) {
            .search-header {
                padding: 20px 16px;
                border-radius: 12px;
            }
            .search-header h3 { font-size: 1.2rem; }
            .search-box input {
                height: 48px;
                font-size: 0.9rem;
                padding-left: 38px;
            }
            .search-box .btn-search {
                padding: 0 15px;
                font-size: 0.85rem;
            }
            .mentor-card .card-body { padding: 14px; }
            .mentor-avatar { width: 48px; height: 48px; font-size: 1.2rem; }
            .search-type-badge { font-size: 0.7rem; padding: 4px 10px; }
            .popular-course { font-size: 0.75rem; padding: 4px 10px; }
            .ai-suggestions { max-height: 200px; }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-3 mt-md-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <!-- Search Header -->
                <div class="search-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><i class="bi bi-robot"></i> AI-Powered Search</h3>
                            <p>Connect with the perfect mentor using our intelligent search</p>
                        </div>
                        <div>
                            <span class="ai-chip"><i class="bi bi-cpu"></i> AI Enabled</span>
                        </div>
                    </div>
                    
                    <!-- Search Form -->
                    <form method="GET" action="" class="search-box" id="searchForm">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="form-control" id="searchInput" name="search" 
                               placeholder="Search a course code or name, e.g. CSC101 or Database Systems" 
                               value="<?php echo htmlspecialchars($search_term); ?>" autofocus
                               autocomplete="off">
                        <input type="hidden" name="search_type" id="search_type" value="<?php echo $search_type; ?>">
                        <input type="hidden" name="sort" id="sort" value="<?php echo $sort_by; ?>">
                        <input type="hidden" name="min_rating" id="min_rating" value="<?php echo $min_rating; ?>">
                        <input type="hidden" name="max_rate" id="max_rate" value="<?php echo $max_rate; ?>">
                        <button type="submit" class="btn btn-primary btn-search">
                            <i class="bi bi-search"></i> Search
                        </button>
                        
                        <!-- AI Suggestions Dropdown -->
                        <div class="ai-suggestions" id="aiSuggestions"></div>
                    </form>
                    
                    <!-- AI Understanding -->
                    <?php if (!empty($search_term)): ?>
                        <div class="ai-understanding">
                            <i class="bi bi-cpu ai-icon"></i>
                            <span><?php echo $ai_understood; ?></span>
                            <?php if (isset($search_results[0]['score'])): ?>
                                <span class="ai-score-indicator ms-2">
                                    <i class="bi bi-star-fill"></i> Top match score: <?php echo round($search_results[0]['score'] ?? 0); ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Search Type Badges -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="search-type-badge <?php echo $search_type === 'all' ? 'active' : ''; ?>" aria-pressed="<?php echo $search_type === 'all' ? 'true' : 'false'; ?>"
                          onclick="setSearchType('all')">
                        <i class="bi bi-search"></i> All
                    </button>
                    <button type="button" class="search-type-badge <?php echo $search_type === 'course' ? 'active' : ''; ?>" aria-pressed="<?php echo $search_type === 'course' ? 'true' : 'false'; ?>"
                          onclick="setSearchType('course')">
                        <i class="bi bi-book"></i> Course
                    </button>
                    <button type="button" class="search-type-badge <?php echo $search_type === 'mentor' ? 'active' : ''; ?>" aria-pressed="<?php echo $search_type === 'mentor' ? 'true' : 'false'; ?>"
                          onclick="setSearchType('mentor')">
                        <i class="bi bi-person"></i> Mentor
                    </button>
                    <button type="button" class="search-type-badge <?php echo $search_type === 'programme' ? 'active' : ''; ?>" aria-pressed="<?php echo $search_type === 'programme' ? 'true' : 'false'; ?>"
                          onclick="setSearchType('programme')">
                        <i class="bi bi-mortarboard"></i> Programme
                    </button>
                    <button type="button" class="search-type-badge <?php echo $search_type === 'topic' ? 'active' : ''; ?>" aria-pressed="<?php echo $search_type === 'topic' ? 'true' : 'false'; ?>"
                          onclick="setSearchType('topic')">
                        <i class="bi bi-tag"></i> Topic
                    </button>
                </div>
                
                <!-- Popular Courses -->
                <?php if (empty($search_term)): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="mb-2"><i class="bi bi-fire"></i> Popular Courses</h6>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($popular_courses as $course): ?>
                                    <a href="?search=<?php echo urlencode($course['course_code']); ?>&search_type=course" 
                                       class="popular-course">
                                        <?php echo htmlspecialchars($course['course_code']); ?>
                                        <span class="count"><?php echo htmlspecialchars($course['course_name']); ?> · <?php echo $course['mentor_count']; ?> mentors</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <?php if (!empty($search_term)): ?>
                    <div class="filter-section">
                        <div class="row g-2 g-md-3 align-items-end">
                            <div class="col-6 col-md-3">
                                <label class="form-label">Sort By</label>
                                <select class="form-select" onchange="updateSort(this.value)">
                                    <option value="relevance" <?php echo $sort_by === 'relevance' ? 'selected' : ''; ?>>🤖 AI Relevance</option>
                                    <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>⭐ Rating</option>
                                    <option value="rate_low" <?php echo $sort_by === 'rate_low' ? 'selected' : ''; ?>>💰 Rate: Low to High</option>
                                    <option value="rate_high" <?php echo $sort_by === 'rate_high' ? 'selected' : ''; ?>>💰 Rate: High to Low</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Min Rating</label>
                                <select class="form-select" onchange="updateMinRating(this.value)">
                                    <option value="0" <?php echo $min_rating == 0 ? 'selected' : ''; ?>>Any Rating</option>
                                    <option value="4" <?php echo $min_rating == 4 ? 'selected' : ''; ?>>4+ ★</option>
                                    <option value="4.5" <?php echo $min_rating == 4.5 ? 'selected' : ''; ?>>4.5+ ★</option>
                                    <option value="3" <?php echo $min_rating == 3 ? 'selected' : ''; ?>>3+ ★</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Max Rate (K/hr)</label>
                                <select class="form-select" onchange="updateMaxRate(this.value)">
                                    <option value="500" <?php echo $max_rate == 500 ? 'selected' : ''; ?>>Any</option>
                                    <option value="50" <?php echo $max_rate == 50 ? 'selected' : ''; ?>>K50</option>
                                    <option value="80" <?php echo $max_rate == 80 ? 'selected' : ''; ?>>K80</option>
                                    <option value="100" <?php echo $max_rate == 100 ? 'selected' : ''; ?>>K100</option>
                                    <option value="150" <?php echo $max_rate == 150 ? 'selected' : ''; ?>>K150</option>
                                    <option value="200" <?php echo $max_rate == 200 ? 'selected' : ''; ?>>K200</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <a href="search.php" class="btn btn-outline-secondary w-100">Clear All</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Search Results -->
                <?php if (!empty($search_term)): ?>
                    <!-- Search Stats -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="search-stats">
                            <span class="stat-item">
                                <strong><?php echo $search_count; ?></strong> mentor(s) found
                            </span>
                            <span class="stat-item">
                                <i class="bi bi-person"></i> <?php echo $total_mentors; ?> total mentors
                            </span>
                            <?php if ($search_term): ?>
                                <span class="stat-item">
                                    <i class="bi bi-search"></i> "<?php echo htmlspecialchars($search_term); ?>"
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($sort_by === 'relevance' && $search_count > 0): ?>
                            <span class="badge bg-primary">
                                <i class="bi bi-robot"></i> AI Sorted by Relevance
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Results Grid -->
                    <?php if ($search_results): ?>
                        <div class="row g-3">
                            <?php foreach ($search_results as $index => $mentor): 
                                $avatar_bg = 'bg-1';
                                $bg_classes = ['bg-1', 'bg-2', 'bg-3', 'bg-4', 'bg-5', 'bg-6'];
                                $avatar_bg = $bg_classes[($mentor['id'] - 1) % count($bg_classes)];
                            ?>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="card mentor-card h-100">
                                        <?php if (isset($mentor['score']) && $mentor['score'] > 0): ?>
                                            <div class="relevance-badge">
                                                <i class="bi bi-robot"></i> <?php echo round($mentor['score']); ?>% match
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="mentor-avatar <?php echo $avatar_bg; ?> me-3">
                                                    <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($mentor['name']); ?></h6>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <span class="badge bg-secondary"><?php echo ucfirst($mentor['programme'] ?? 'Student'); ?></span>
                                                        <?php if (isset($mentor['year_of_study']) && $mentor['year_of_study']): ?>
                                                            <span class="badge bg-light text-dark">Year <?php echo $mentor['year_of_study']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Rating -->
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <?php if ($mentor['avg_rating']): ?>
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star-fill <?php echo $i <= round($mentor['avg_rating']) ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($mentor['avg_rating'], 1); ?></small>
                                                    <small class="text-muted">(<?php echo $mentor['total_reviews'] ?? 0; ?> reviews)</small>
                                                <?php else: ?>
                                                    <span class="text-muted">No reviews yet</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Courses -->
                                            <div class="mb-2">
                                                <span class="mentor-tag course-tag">
                                                    <i class="bi bi-book"></i>
                                                    <?php echo htmlspecialchars($mentor['course_code']); ?>
                                                    <span class="d-block"><?php echo htmlspecialchars($mentor['course_name'] ?? $mentor['course_code']); ?></span>
                                                </span>
                                                <?php if (isset($mentor['total_courses']) && $mentor['total_courses'] > 1): ?>
                                                    <span class="mentor-tag">
                                                        <i class="bi bi-plus-circle"></i> +<?php echo $mentor['total_courses'] - 1; ?> more
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Stats -->
                                            <div class="row g-1 mb-3">
                                                <div class="col-4">
                                                    <div class="stat-badge">
                                                        <div class="number"><?php echo $mentor['completed_sessions'] ?? 0; ?></div>
                                                        <div class="label">Sessions</div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="stat-badge">
                                                        <div class="number"><?php echo $mentor['total_reviews'] ?? 0; ?></div>
                                                        <div class="label">Reviews</div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="stat-badge">
                                                        <div class="number text-primary"><?php echo formatCurrency($mentor['rate_per_hour']); ?></div>
                                                        <div class="label">/hour</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mentor-availability mb-3">
                                                <div class="small fw-semibold mb-1"><i class="bi bi-clock me-1"></i>Weekly availability</div>
                                                <?php if (!empty($availability_by_mentor[$mentor['id']])): ?>
                                                    <div class="small text-muted">
                                                        <?php foreach ($availability_by_mentor[$mentor['id']] as $slot): ?>
                                                            <div>
                                                                <?php echo htmlspecialchars($weekday_names[(int)$slot['day_of_week']] ?? ''); ?>:
                                                                <?php echo date('g:i A', strtotime($slot['start_time'])); ?>–<?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="small text-muted">No weekly hours set yet.</div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Action Button -->
                                            <?php if (!empty($availability_by_mentor[$mentor['id']])): ?>
                                                <a href="book.php?mentor_id=<?php echo $mentor['id']; ?>&course=<?php echo urlencode($mentor['course_code']); ?>" 
                                                   class="btn btn-primary w-100">
                                                    <i class="bi bi-calendar-plus"></i> Book Session
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                                    <i class="bi bi-calendar-x"></i> Availability not posted
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="bi bi-robot icon"></i>
                            <h5 class="mt-3">No mentors found</h5>
                            <p class="text-muted">AI couldn't find mentors matching your search criteria.</p>
                            <div class="mt-3">
                                <a href="search.php" class="btn btn-primary">Clear Search</a>
                                <button class="btn btn-outline-secondary ms-2" onclick="history.back()">Go Back</button>
                            </div>
                            <div class="mt-4">
                                <h6>Try these suggestions:</h6>
                                <ul class="list-unstyled text-muted">
                                    <li>• Use natural language like "I need help with programming"</li>
                                    <li>• Search by course code like "CSC101"</li>
                                    <li>• Search by mentor name</li>
                                    <li>• Try a broader search term</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- No Search - Show Available Mentors -->
                    <div class="text-center py-4">
                        <div class="display-1 text-muted">
                            <i class="bi bi-robot"></i>
                        </div>
                        <h5 class="mt-3">AI-Powered Mentor Discovery</h5>
                        <p class="text-muted">Search naturally like you're asking a person.</p>
                        
                        <div class="mt-4">
                            <h6 class="text-muted small">Example searches:</h6>
                            <div class="d-flex flex-wrap justify-content-center gap-2 mt-2">
                                <a href="?search=I need help with CSC101&search_type=all" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-robot"></i> "I need help with CSC101"
                                </a>
                                <a href="?search=Find a math tutor&search_type=all" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-robot"></i> "Find a math tutor"
                                </a>
                                <a href="?search=Looking for programming mentor&search_type=all" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-robot"></i> "Looking for programming mentor"
                                </a>
                                <a href="?search=Need accounting help&search_type=all" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-robot"></i> "Need accounting help"
                                </a>
                            </div>
                        </div>
                        
                        <!-- Quick search by course -->
                        <div class="mt-4">
                            <h6 class="text-muted small">Or search by course:</h6>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <?php foreach (array_slice($popular_courses, 0, 6) as $course): ?>
                                    <a href="?search=<?php echo urlencode($course['course_code']); ?>&search_type=course" 
                                       class="btn btn-outline-secondary btn-sm">
                                        <?php echo htmlspecialchars($course['course_code']); ?> · <?php echo htmlspecialchars($course['course_name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // AI SUGGESTIONS
        // ============================================
        const searchInput = document.getElementById('searchInput');
        const suggestionsDiv = document.getElementById('aiSuggestions');
        let suggestionsTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(suggestionsTimeout);
            const query = this.value;
            
            if (query.length < 2) {
                suggestionsDiv.classList.remove('show');
                return;
            }
            
            suggestionsTimeout = setTimeout(function() {
                // Get suggestions via AJAX
                fetch('search_suggestions.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.suggestions && data.suggestions.length > 0) {
                            suggestionsDiv.replaceChildren();
                            data.suggestions.forEach(function(s) {
                                const badgeClass = s.type === 'course' ? 'bg-primary' : 
                                                  s.type === 'mentor' ? 'bg-success' : 
                                                  'bg-warning text-dark';
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'suggestion-item w-100 text-start border-0 bg-white';
                                item.dataset.value = s.value;
                                item.dataset.type = s.type;
                                const badge = document.createElement('span');
                                badge.className = 'badge ' + badgeClass;
                                badge.textContent = s.type;
                                const text = document.createElement('span');
                                text.className = 'suggestion-text ms-2';
                                text.textContent = s.text;
                                item.append(badge, text);
                                suggestionsDiv.appendChild(item);
                            });
                            suggestionsDiv.classList.add('show');
                        } else {
                            suggestionsDiv.classList.remove('show');
                        }
                    })
                    .catch(() => {
                        suggestionsDiv.classList.remove('show');
                    });
            }, 300);
        });
        
        document.addEventListener('click', function(e) {
            const suggestion = e.target.closest('.suggestion-item');
            if (suggestion && suggestionsDiv.contains(suggestion)) {
                selectSuggestion(suggestion.dataset.value, suggestion.dataset.type);
                return;
            }
            if (!e.target.closest('.search-box')) {
                suggestionsDiv.classList.remove('show');
            }
        });
        
        function selectSuggestion(value, type) {
            searchInput.value = value;
            const searchTypes = { course: 'course', mentor: 'mentor', topic: 'topic' };
            if (searchTypes[type]) {
                document.getElementById('search_type').value = searchTypes[type];
            }
            suggestionsDiv.classList.remove('show');
            document.getElementById('searchForm').submit();
        }
        
        // ============================================
        // SEARCH TYPE FUNCTIONS
        // ============================================
        function setSearchType(type) {
            document.getElementById('search_type').value = type;
            document.getElementById('searchForm').submit();
        }
        
        function updateSort(value) {
            document.getElementById('sort').value = value;
            document.getElementById('searchForm').submit();
        }
        
        function updateMinRating(value) {
            document.getElementById('min_rating').value = value;
            document.getElementById('searchForm').submit();
        }
        
        function updateMaxRate(value) {
            document.getElementById('max_rate').value = value;
            document.getElementById('searchForm').submit();
        }
        
        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            // Ctrl + / to focus search
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                searchInput.blur();
                suggestionsDiv.classList.remove('show');
            }
        });
        
        // ============================================
        // AUTO-FOCUS ON MOBILE
        // ============================================
        if (window.innerWidth < 768) {
            searchInput.addEventListener('focus', function() {
                this.select();
            });
        }
    </script>
</body>
</html>