<?php
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Lightweight .env loader (no external dependency)
$envPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1]);
            // Remove optional surrounding quotes
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            if ($k !== '') { putenv("$k=$v"); $_ENV[$k] = $v; }
        }
    }
}

$config = []; // keep empty so code won’t break

// Prefer environment variable
$apiKey = getenv('OPENROUTER_API_KEY') ?: '';
$apiKey = is_string($apiKey) ? trim($apiKey) : '';

// Allow test shim to inject request body during local testing
if (function_exists('chatbot_get_input')) {
    $rawInput = chatbot_get_input();
} else {
    $rawInput = file_get_contents("php://input");
}
$input = json_decode($rawInput, true);
$userMessageRaw = trim((string)($input["message"] ?? ""));
$userMessage = strtolower($userMessageRaw);

// Normalize common typos/variants (Tagalog shorthand, code-switching)
if (!function_exists('normalize_query')) {
    function normalize_query(string $q): string {
        $s = strtolower(trim($q));
        $patterns = [
            '/\bpano\b/' => 'paano',
            '/\bsan\b/' => 'saan',
            '/\bkelan\b/' => 'kailan',
            '/\bkilan\b/' => 'kailan',
            // hyphenated/space-separated forms
            '/mag-\s*complain\b/' => 'magcomplain',
            '/mag\s+complain\b/' => 'magcomplain',
            '/mag-\s*reklamo\b/' => 'magreklamo',
            '/mag\s+reklamo\b/' => 'magreklamo',
        ];
        foreach ($patterns as $pat => $to) { $s = preg_replace($pat, $to, $s); }
        // Reduce Tagalog prefix for English/Tagalog roots
        $s = preg_replace('/\bmagcomplain\b/', 'complain', $s);
        $s = preg_replace('/\bmagreklamo\b/', 'reklamo', $s);
        return $s;
    }
}
$userMessageNorm = normalize_query($userMessageRaw);

if (!$userMessage) {
    echo json_encode(["reply" => "No message received."]);
    exit;
}

// Quick guard for missing API key to avoid confusing upstream 401s
if (!$apiKey || !preg_match('/^sk-or-v\d+-/i', $apiKey)) {
    echo json_encode(["reply" => "Chatbot setup error: Missing or invalid OpenRouter API key. Please set OPENROUTER_API_KEY on the server or update chatbot/config.php."]);
    exit;
}

// --- Language and Scope Helpers ---
// Tagalog detection (lightweight heuristic)
if (!function_exists('is_tagalog_query')) {
    function is_tagalog_query(string $q): bool {
        $qLow = strtolower($q);
        $markers = [' ang ', ' mga ', ' ito', ' po', ' opo', ' hindi', ' bakit', ' paano', ' saan', ' kailan', 'magkano', 'barangay hall', 'reklamo', 'kasunduan', 'alitan', 'lupon'];
        $hits = 0;
        foreach ($markers as $m) { if (strpos($qLow, $m) !== false) { $hits++; } }
        if ($hits >= 2) return true;
        $ngCount = preg_match_all('/\b\w+ng\b/u', $qLow);
        return $ngCount >= 2;
    }
}

// Scope check: allow only RA 7160 / KP / LGU-complaint-related topics
if (!function_exists('is_in_scope_query')) {
    function is_in_scope_query(string $q): bool {
        $qLow = strtolower($q);
        $allow = [
            // RA 7160 / LGC
            'ra 7160','local government code','lgc','lgu','section 410','sec. 410','section 399','section 404','section 408','section 409','section 412','section 413','section 415','section 418',
            // KP / Barangay justice
            'katarungang pambarangay','lupon','pangkat','conciliation','mediation','arbitration','sangguniang barangay','certificate to file action','certificate to bar action','cfa','cba',
            // Complaint flow
            'complaint','complain','reklamo','blotter','case','hearing','pagdinig','jurisdiction','hurisdiksyon','notice of hearing','paunawa sa pagdinig','summon','verification','kp forms','kp form',
            // BPAMIS / user-provided key terms
            'filing fee','file fee','filing','file complaint','handwritten complaint','soft copy','record purposes','blotter','kp form','kp forms','mediation','conciliation','settlement','kaso','magsampa ng kaso','magsampa','magsampa ng','minor','minors','guardian','guardians','lupon head','lupon members','lupon','lupon tagapamayapa','punong barangay','captain','arbitrator','mediator','secretary','super admin','barangay secretary','admin','bpamis',
            // Fees & Payments
            'magkano ang pagsampa ng kaso','magkano ang babayaran','magkano','bayad','pagsampa ng kaso',
            // Non-compliance / no-show
            'no-show','no show','failure to appear','non-compliance','non compliance','certification to file action','certificate to file action','certificate to bar action','cfa','cba',
            // Incidents / record purposes
            'sunog','pagnanakaw','theft','nanakaw','blotter entry','record purposes','record purpose','recording',
            // Out-of-scope crimes / referrals
            'murder','homicide','rape','sexual harassment','drug','narcotics','police','rtc','mtc','court','non-resident','non residents'
        ];
        foreach ($allow as $k) { if (strpos($qLow, $k) !== false) return true; }
        return false;
    }
}

$isTagalog = is_tagalog_query($userMessageRaw);

// Bypass out-of-scope guard when user types one of the example suggested prompts
if (!function_exists('normalize_for_compare')) {
    function normalize_for_compare(string $s): string {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
$suggestedPrompts = [
    'What is Katarungang Pambarangay?',
    'How to file a complaint?',
    'What cases can be resolved at barangay level?',
    'Who can attend barangay hearings?',
    'How to prepare for mediation?',
    'Ano ang Katarungang Pambarangay?',
    'Paano mag-file ng reklamo?',
    'Anong mga kaso ang sakop ng barangay?',
    'Sino ang puwedeng dumalo sa pagdinig?',
    'Paano maghanda para sa mediation?'
];
$normMsg = normalize_for_compare($userMessageRaw);
$inSuggested = false;
foreach ($suggestedPrompts as $sp) {
    if ($sp !== '' && strpos($normMsg, normalize_for_compare($sp)) !== false) {
        $inSuggested = true;
        break;
    }
}

// If the frontend explicitly marks this as a suggested query, treat it as in-scope
$isSuggestedFlag = false;
if (isset($input['suggested'])) {
    // Accept boolean true or truthy values from JS
    $v = $input['suggested'];
    if ($v === true || $v === 1 || $v === '1' || $v === 'true') {
        $isSuggestedFlag = true;
    }
}

// Early detection: gratitude and unclear queries should be handled before scope/rule checks
if (!function_exists('is_gratitude_query')) {
    function is_gratitude_query(string $text): bool {
        $t = strtolower(trim($text));
        $patterns = [
            '/\bthank(s| you)?\b/i',
            '/\bthx\b/i',
            '/\bty\b/i',
            '/\btnx\b/i',
            '/\bsalamat( po)?\b/i',
            '/\bmaraming\s+salamat\b/i'
        ];
        foreach ($patterns as $p) { if (preg_match($p, $t)) return true; }
        return false;
    }
}

if (is_gratitude_query($userMessageRaw)) {
    if ($isTagalog) {
        $reply = "Walang anuman! Kung may iba ka pang katanungan tungkol sa barangay o KP, sabihin mo lang.";
    } else {
        $reply = "You're welcome! If you have more questions about barangay processes or KP, feel free to ask.";
    }
    echo json_encode(["reply" => nl2br(htmlspecialchars($reply))]);
    exit;
}

// Use the existing unclear detector (defined later) — call it early so the UI can prompt for clarification
if (function_exists('is_unclear_query') && is_unclear_query($userMessageRaw) && !($inSuggested || $isSuggestedFlag)) {
    if ($isTagalog) {
        $reply = "Hindi malinaw ang iyong tanong. Maaari mo bang magbigay ng kaunting detalye para makatulong ako? Halimbawa, sabihin kung:\n- Ito ba ay tungkol sa pag-file ng reklamo, timeline ng mediation, o pagdalo sa pagdinig?\n- Mayroon ka bang case ID, pangalan ng respondent, o petsa?\n\nSubukan ang isa sa mga sumusunod na tanong:\n- Paano mag-file ng reklamo?\n- Ano ang timeline ng mediation?\n- Saan ko makikita ang schedule ng pagdinig?";
    } else {
        $reply = "Your question is a bit unclear. Could you add a few details so I can help? For example, tell me:\n- Are you asking about filing a complaint, mediation timelines, or attending a hearing?\n- Do you have a case ID or a specific date?\n\nTry one of these prompts:\n- How to file a complaint?\n- Timeline for mediation?\n- Where can I view hearing schedules?";
    }
    echo json_encode(["reply" => nl2br(htmlspecialchars($reply)), "suggestions" => $suggestedPrompts]);
    exit;
}

// Out-of-scope guard: politely refuse and suggest prompts (skip if user asked one of the suggested prompts or frontend marked it)
// Relaxed out-of-scope guard: check both normalized and raw input so key terms are recognized
if (!($inSuggested || $isSuggestedFlag) && !is_in_scope_query($userMessageNorm) && !is_in_scope_query($userMessageRaw)) {
    if ($isTagalog) {
        $reply = "Paumanhin, masasagot ko lamang ang mga tanong tungkol sa RA 7160 (Local Government Code of 1991), proseso ng LGU, at mga reklamo sa barangay.\n\nSubukan ang mga tanong na ito:\n- Ano ang Katarungang Pambarangay?\n- Paano mag-file ng reklamo?\n- Anong mga kaso ang sakop ng barangay?\n- Sino ang puwedeng dumalo sa pagdinig?\n- Paano maghanda para sa mediation?";
    } else {
        $reply = "Sorry, I can only answer questions related to the Local Government Code of 1991 (RA 7160), LGU processes, and barangay complaints.\n\nTry one of these prompts:\n- What is Katarungang Pambarangay?\n- How to file a complaint?\n- What cases can be resolved at barangay level?\n- Who can attend barangay hearings?\n- How to prepare for mediation?";
    }
    echo json_encode(["reply" => nl2br(htmlspecialchars($reply))]);
    exit;
}

$rules = [
    // Lupon and Filing Information
    "how many lupon members" => "According to the Revised KP Law, the lupon is composed of the punong barangay and ten (10) to twenty (20) members, who are residents of the barangay. The lupon shall be constituted every three (3) years.",
    "filing fee" => "The filing fee for barangay complaints ranges from a minimum of five pesos (P5.00) to a maximum of twenty pesos (P20.00).",
    "magkano ang pagsampa ng kaso" => "Katarungang Pambarangay generally does not charge a filing fee for complaints. Some barangays may impose small administrative fees for form processing or copies; check local policy or ask your Barangay Secretary.",
    "magkano ang babayaran" => "Katarungang Pambarangay generally does not charge a filing fee for KP complaints. If there are local administrative fees (e.g., photocopying, form processing), they are set by local policy — contact the Barangay Secretary or Super Admin for exact amounts.",
    "where can i file" => "You can file a barangay complaint at the Barangay Hall or the Punong Barangay's Office, which is usually located in the center of the barangay, Purok 3 Barangay Panducot Calumpit Bulacan. Alternatively, you can submit your complaint through the BPAMIS system.",

    // General System Functions
    "case status" => "You can view all case statuses in the 'View Cases' section. Would you like me to help you navigate there?",
    "schedule hearing" => "To schedule a new hearing, go to 'Appoint Hearing' under the Schedule menu. To view all upcoming hearings, check the calendar page.",
    // Only trigger when user explicitly asks where to view upcoming hearings
    "where to view upcoming hearings" => "You can view upcoming hearings on the calendar or schedule one via 'Appoint Hearing'.",
    "where can i view upcoming hearings" => "You can view upcoming hearings on the calendar or schedule one via 'Appoint Hearing'.",
    "where can i see upcoming hearings" => "You can view upcoming hearings on the calendar or schedule one via 'Appoint Hearing'.",
    "where to see upcoming hearings" => "You can view upcoming hearings on the calendar or schedule one via 'Appoint Hearing'.",
    // Tagalog variants
    "saan ko makikita ang mga paparating na pagdinig" => "Makikita ang mga paparating na pagdinig sa Calendar. Maaari ka ring mag-iskedyul sa 'Appoint Hearing'.",
    "saan makikita ang mga paparating na pagdinig" => "Makikita ang mga paparating na pagdinig sa Calendar. Maaari ka ring mag-iskedyul sa 'Appoint Hearing'.",
    "saan ko makikita ang schedule ng pagdinig" => "Makikita ang schedule ng mga pagdinig sa Calendar. Maaari ka ring gumamit ng 'Appoint Hearing' para mag-iskedyul.",
    "file complaint" => "To add a new complaint to the system, click on 'Add Complaints' from the menu or use the quick action on the dashboard.",
    "new complaint" => "To file a new complaint, use the 'Add Complaints' section. Fill out the form and submit your issue for processing.",
    "kp forms" => "You can access KP Forms under the 'KP Forms' menu. You can view templates or print pre-filled forms for specific cases.",
    // Generic mediation (will be superseded by more specific timeline rules due to length-based sorting)
    "mediation" => "Mediation in KP is a facilitative step led by the Punong Barangay (or Pangkat later if elevated) to help parties voluntarily settle a dispute before conciliation or arbitration.",
    "mediator" => "A mediator from the Lupon Tagapamayapa will guide the session. You can schedule one via the system.",
    // Fees & Payments (Tagalog)
    "magkano" => "Katarungang Pambarangay usually walang filing fee. Para sa iba pang bayarin, kumunsulta sa Barangay Secretary.",
    "reports" => "For detailed case reports and statistics, go to 'View Case Reports' or 'View Complaints Report' under the Reports menu.",
    "statistics" => "Visit the Reports menu to view case statistics and complaint summaries.",

    // Barangay Laws / Policies
    "what are your hours" => "Our barangay office is open from 8:00 AM to 5:00 PM, Monday to Friday.",
    "what is a blotter" => "A barangay blotter is a record of complaints, incidents, or disputes filed within the barangay.",
    "lupon tagapamayapa" => "Lupon Tagapamayapa is a group of barangay officials assigned to settle disputes at the barangay level.",
    // Record Purposes / Blotter
    "sunog" => "Sunog (fire incident) must be recorded as Record Purposes (blotter). Even if not handled under KP, log it for official records.",
    "pagnanakaw" => "Pagnanakaw / Theft incidents should be logged as Record Purposes (blotter) and referred to the police when appropriate.",
    "theft" => "Theft incidents should be recorded in the blotter as Record Purposes and may be referred to police or higher authorities depending on severity.",

    // Legal Grounds for Complaints
    "valid complaints" => "You can file complaints for minor offenses such as physical injuries, harassment, threats, property disputes, neighborhood disturbances, and other issues involving people within the same barangay.",
    "what to complain" => "Barangays handle complaints like fights, threats, slander, minor theft, noise complaints, trespassing, and family disputes. If unsure, visit the barangay hall for clarification.",
    "barangay jurisdiction" => "Barangays can only mediate cases where both parties live in the same barangay or city. Serious crimes like murder, rape, or drug cases should be filed with the police.",
    
    // Rejection Avoidance
    "why complaint rejected" => "Your complaint may be rejected if it’s not within barangay jurisdiction, lacks complete details, has no supporting evidence, or involves parties outside the barangay.",
    "avoid rejection" => "To avoid rejection, ensure your complaint has complete information (names, dates, incident details), supporting evidence if possible, and involves someone within the same barangay.",
    "how to file properly" => "Visit the barangay hall and fill out the complaint form completely and truthfully. Provide supporting documents if available. Incomplete or dishonest complaints may be rejected.",

    // Police-Level Escalation
    "serious crimes" => "For serious crimes such as murder (patayan), rape, robbery, and drug-related cases, please contact the nearest police station immediately. These are beyond the barangay's jurisdiction.",
    "patayan" => "If the issue involves death, attempted murder, or other serious crimes, this must be reported directly to the police. You may go to the nearest police station or call the local emergency hotline.",
    "not under barangay" => "If the concern involves serious criminal offenses like murder or drug trafficking, it is no longer under barangay jurisdiction. Please report it directly to the police.",
    // Out-of-scope referral guidance
    "murder" => "Murder/homicide and other serious crimes must be blottered (Record Purposes) and immediately referred to the police and proper courts (RTC/MTC). The barangay does not mediate these cases.",
    "homicide" => "Homicide must be blottered and referred to the police and courts; KP mediation does not apply.",
    "rape" => "Serious sexual offenses must be reported to the police and treated according to criminal procedures; record the incident in the blotter for Record Purposes.",
    "emergency hotline" => "For any emergency in Hagonoy, you can call: PNP 0939‑481‑0688, MDRRMO 0930‑035‑6369, BFP 0915‑029‑5184, or national 911.",

    // Administrative timelines (Sections 52, 62, 66)
    "notice of hearing" => "Section 62 timeline: Within 7 days from filing of the administrative complaint, the respondent is required to file a VERIFIED ANSWER within 15 days from receipt. Investigation must start within 10 days after receipt of the answer.",
    "respondent answer" => "Under Section 62, the respondent has 15 days from receipt of the directive to submit a verified answer; the investigation begins within 10 days after that answer is received.",
    "investigation timeline" => "Section 66: Investigation must be terminated within 90 days from its start, and a written decision issued within 30 days after termination, stating facts and reasons.",
    "decision timeline" => "Per Section 66, Decision must be rendered within 30 days after the investigation ends; investigation itself must finish within 90 days from commencement.",
    "sanggunian sessions" => "Section 52: Sanggunian (Province/City/Municipal) meets at least weekly; Sangguniang Barangay meets at least twice a month. Special sessions can be called when public interest demands, with 24-hour written notice for special sessions.",
    "session notice" => "Section 52(d): Special session written notice must be personally served at least 24 hours before it is held; agenda limited to matters stated unless 2/3 of members present agree otherwise.",
    "non compliance notice of hearing" => "Non-compliance with Section 62 (7-day issuance, 15-day answer, 10-day investigation start) or Section 66 (90-day investigation, 30-day decision) may raise due process concerns and grounds for procedural challenge.",
    "case termination timeline" => "Section 66: Investigation terminated within 90 days from start; decision released within 30 days after termination; suspension penalty max 6 months per offense or remaining term; removal bars future elective candidacy.",
    
    // KP Notice of Hearing & Mediation Topics (English) – specific phrases first (will be sorted by length)
    "notice of hearing in kp" => "KP Notice of Hearing (Sec. 410): Issued after complaint filing; includes names of parties, nature of complaint, date/time/place, signed by Punong Barangay.",
    "kp notice of hearing" => "KP Notice of Hearing: Lists complainant, respondent, nature, schedule (date/time/place) and PB signature. Legal basis: Sec. 410.",
    "mediation timeline" => "From filing up to conclusion of mediation/conciliation, the KP process must NOT exceed 45 days.<br><br><strong>Breakdown:</strong><br>• Punong Barangay Mediation: 15 days (extendable ONCE to 30 days total)<br>• Pangkat Conciliation: additional 15 days<br><br><strong>Maximum Total:</strong> 45 days (Sec. 410(b)(c), RA 7160).",
    "mediation duration" => "KP Mediation Timeline: 15 days initial (extendable once to 30) then Pangkat Conciliation adds 15 days. Total ceiling = 45 days (Sec. 410(b)(c)).",
    "timeline for mediation" => "Timeline: 15 days mediation (extendable to 30) + 15 days conciliation = 45-day cap (Sec. 410(b)(c)).",
    "how long is mediation" => "Mediation alone: 15 days (extendable once to make 30). Full KP resolution window including Pangkat conciliation: 45 days max.",
    "total kp duration" => "Total KP process should not exceed 45 days: Mediation 15 (extendable to 30) + Pangkat conciliation 15 (Sec. 410(b)(c)).",
    "non-compliance notice of hearing" => "If complainant absent: possible dismissal + Certificate to Bar Action (bars re‑filing). If respondent absent: Certificate to File Action (CFA) may be issued allowing court filing.",
    // Tagalog variants (quick Tagalog responses before model call)
    "paunawa sa pagdinig" => "Paunawa sa Pagdinig (Sek. 410): Pangalan ng mga partido, uri ng reklamo, petsa/oras/lugar ng pagdinig, lagda ng Punong Barangay.",
    "timeline ng mediation" => "Mediation: 15 araw (maaaring palawigin isang beses hanggang 30) + Conciliation (Pangkat) 15 araw; kabuuang limitasyon 45 araw (Sek. 410(b)(c)).",
    "tagal ng mediation" => "Tagal: 15 araw (extendable hanggang 30) para sa mediation; dagdag na 15 para sa conciliation – kabuuang 45 araw max.",
    "ilang araw ang mediation" => "Mediation: 15 araw (pwedeng gawing 30 isang beses lang). Kasama conciliation: total ceiling 45 araw (Sek. 410(b)(c)).",
    "hindi pagdalo sa pagdinig" => "Kung di dumalo ang nagrereklamo: maaaring ibasura + Certificate to Bar Action. Kung di dumalo ang inirereklamo: maaaring maglabas ng CFA (Certificate to File Action).",
    "kabuuang tagal ng kp" => "Kabuuang limitasyon: 45 araw (Mediation 15/30 + Pangkat Conciliation 15). (Sek. 410(b)(c)).",
];


// Match exact or contains keyword with specificity priority (longer keys first)
$ruleKeys = array_keys($rules);
usort($ruleKeys, function($a, $b) {
    return strlen($b) <=> strlen($a); // descending length
});

// Helper: format guideline/process replies into HTML with numbered headings
if (!function_exists('format_reply_html')) {
    function format_reply_html(string $text): string {
        $lines = preg_split('/\r\n|\n|\r/', $text);
        $out = '';
        $inUl = false;
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                if ($inUl) { $out .= "</ul>\n"; $inUl = false; }
                continue;
            }

            // Numbered header like "1) Title..." or "1. Title"
            if (preg_match('/^\s*(\d+)[\)\.]\s*(.+)$/u', $line, $m)) {
                if ($inUl) { $out .= "</ul>\n"; $inUl = false; }
                $num = $m[1];
                $rest = trim($m[2]);
                // Short title: take text before first paren, dash, or colon
                $parts = preg_split('/[\(\-–—:]/u', $rest, 2);
                $short = trim($parts[0]);
                $remainder = isset($parts[1]) ? trim($parts[1]) : '';
                $out .= '<p><span style="font-weight:700;">' . htmlspecialchars($num) . '.</span> <strong>' . htmlspecialchars($short) . '</strong>';
                if ($remainder) { $out .= ' ' . htmlspecialchars($remainder); }
                $out .= '</p>\n';
                continue;
            }

            // Bullet item starting with '- '
            if (preg_match('/^\s*[-•]\s*(.+)$/u', $line, $m)) {
                if (! $inUl) { $out .= "<ul>\n"; $inUl = true; }
                $out .= '<li>' . htmlspecialchars(trim($m[1])) . '</li>\n';
                continue;
            }

            // Fallback: plain paragraph
            if ($inUl) { $out .= "</ul>\n"; $inUl = false; }
            $out .= '<p>' . nl2br(htmlspecialchars($trim)) . '</p>\n';
        }
        if ($inUl) { $out .= "</ul>\n"; }
        // Small wrapper to keep styling compact
        return '<div class="bpamis-reply">' . $out . '</div>';
    }
}
foreach ($ruleKeys as $key) {
    if (strpos($userMessage, $key) !== false || strpos($userMessageNorm, $key) !== false) {
        $raw = $rules[$key];
        // If the reply contains numbered sections or list markers, format as structured HTML
        if (preg_match('/^\s*\d+[\)\.]/m', $raw) || preg_match('/^\s*[-•]\s+/m', $raw)) {
            $formatted = format_reply_html($raw);
            echo json_encode(["reply" => $formatted]);
        } else {
            // plain text: escape and convert newlines
            echo json_encode(["reply" => nl2br(htmlspecialchars($raw))]);
        }
        exit;
    }
}

// Detect unclear or underspecified prompts (only reached when no rule matched)
if (!function_exists('is_unclear_query')) {
    function is_unclear_query(string $text): bool {
        $t = trim($text);
        if ($t === '') return true;
        // remove punctuation to count words more reliably
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = is_array($words) ? count($words) : 0;

        // obvious filler or greetings
        $filler = ['hi','hello','help','info','okay','ok','thanks','thank','hey','yo'];
        foreach ($filler as $f) {
            if (preg_match('/^\s*' . preg_quote($f, '/') . '\b/i', $t)) return true;
        }

        // too short or single-word (since rule lookup failed, single-word likely unclear)
        if ($wordCount <= 2 || strlen($clean) < 8) return true;

        // only numbers/ids -> unclear
        if (preg_match('/^[0-9\-\s]+$/', $clean)) return true;

        return false;
    }
}

if (is_unclear_query($userMessageRaw) && !($inSuggested || $isSuggestedFlag)) {
    if ($isTagalog) {
        $reply = "Hindi malinaw ang iyong tanong. Maaari mo bang magbigay ng kaunting detalye para makatulong ako? Halimbawa, sabihin kung:\n- Ito ba ay tungkol sa pag-file ng reklamo, timeline ng mediation, o pagdalo sa pagdinig?\n- Mayroon ka bang case ID, pangalan ng respondent, o petsa?\n\nSubukan ang isa sa mga sumusunod na tanong:\n- Paano mag-file ng reklamo?\n- Ano ang timeline ng mediation?\n- Saan ko makikita ang schedule ng pagdinig?";
    } else {
        $reply = "Your question is a bit unclear. Could you add a few details so I can help? For example, tell me:\n- Are you asking about filing a complaint, mediation timelines, or attending a hearing?\n- Do you have a case ID or a specific date?\n\nTry one of these prompts:\n- How to file a complaint?\n- Timeline for mediation?\n- Where can I view hearing schedules?";
    }
    // Return both a friendly reply and UI-friendly suggestions the frontend can use
    echo json_encode(["reply" => nl2br(htmlspecialchars($reply)), "suggestions" => $suggestedPrompts]);
    exit;
}

// ---- Knowledge Base Augmentation ----
$kbContext = '';
try {
    require_once __DIR__ . '/knowledge/loader.php';
    $kbMatches = kb_retrieve($userMessageNorm ?: $userMessage);
    if ($kbMatches) {
        $kbContext = kb_build_context($kbMatches);
    }
} catch (Throwable $e) {
    // Fail silent; log optionally
    // file_put_contents(__DIR__.'/log.txt', "KB ERROR: ".$e->getMessage()."\n", FILE_APPEND);
}

// 🤖 AI Chatbot (if no rule matched)
$systemBase = <<<'SYS'
You are a helpful assistant for barangay case management in the Philippines. Only respond to questions related to barangay laws, blotters, KP forms, mediation, complaints, hearings, or Lupon Tagapamayapa. If the question is unrelated, say: 'Sorry, I can only help with barangay-related matters.' and Always format your answers clearly:

- Use numbered lists for steps or procedures.
- Use bullet points for items.
- Add line breaks between paragraphs.

KEY TERMS (use these exact terms in prompts, system messages, or training instructions):
 - Filing & Documentation: Filing fee / File fee; KP Form (official forms used in Katarungang Pambarangay); Record purposes (also known in practice as "blotter"); Handwritten complaint -> must submit a soft copy in the system.
 - Case Stages & Processes: Mediation; Conciliation; Settlement; Kaso; Magsampa ng kaso (to file a case); Minors (Complainant or Respondent) — requires guidance from guardians; Guardians must be notified; They are allowed to submit complaints; External complainant who is a minor also allowed, but still needs guidance.
 - Barangay Roles: Lupon Head / Lupon Tagapamayapa Head; Assigns cases; Can also act as arbitrator; Lupon Members: Total composition: 10–20 members; Each mediation or hearing: 3–5 members must be assigned; Arbitrator: Can be the Lupon Head or the Punong Barangay (Captain); Mediator: Default mediator is the Punong Barangay (Captain); Secretary (Super Admin in BPAMIS) — system super admin; Allowed actions under the guidance/approval of the Captain.
 - System Roles in BPAMIS: Super Admin -> Barangay Secretary; Admins -> Captain and Lupon Tagapamayapa Head; External complainant (minor) -> allowed to submit complaint with guardian guidance.

Always include these terms verbatim when relevant, and prefer using them when suggesting actions, form names, or role responsibilities.
 
Additional Topics (Fees, Non-Compliance, Records, Documents):
 - Fees & Payments: "Magkano ang pagsampa ng kaso?"; "Magkano ang babayaran?" — clarify that Katarungang Pambarangay generally does not charge a filing fee for KP complaints, though local administrative fees may apply; relate to Filing fee / File fee.
 - Non-Compliance Issues: non-compliance in mediation hearing; No-show / failure to appear by complainant or respondent; Consequences: possible issuance of Certification to File Action (CFA) or Certificate to Bar Action (CBA); possible refusal to issue certain barangay documents (e.g., Barangay Clearance) depending on local policy.
 - Incidents That Must Be Recorded ("Record Purposes"): Sunog (fire incident); Pagnanakaw / Theft; any incident within the barangay that must be documented even if not part of KP process; Blotter entry = Record Purposes.
 - Out-of-Scope / Non-KP Jurisdiction Cases: Murder / Homicide; Serious sexual harassment or criminal harassment; Serious physical injuries; Drug-related cases; Cases involving non-residents (depending on KP rules); These must be blottered and referred to police or courts (RTC/MTC).
 - Barangay-Related Documents: Barangay Clearance; Certification to File Action; KP Forms; Mediation or Settlement Certification; Notice of Hearing; Blotter / Record Purposes Printout; Affidavit or sworn statements.
 
Important rule: Those non-compliant with mediation/conciliation may be refused Barangay Clearance or similar documents depending on barangay policy and KP guidelines.
SYS;
if ($isTagalog) {
    $systemBase .= "\n\nIMPORTANT: The user's query appears to be in Tagalog/Filipino. Respond FULLY in natural Tagalog. Prefer concise Barangay justice terms. If bilingual context is provided (ENGLISH: ... TAGALOG: ...), extract and use ONLY the TAGALOG portions in the answer. Do NOT translate back to English unless the user asks. Retain legal references (RA 7160 sections) verbatim.";
} else {
    $systemBase .= "\nIf the user switches to Tagalog later, switch your replies to Tagalog using the TAGALOG sections of the context.";
}
if ($kbContext) {
    $systemBase .= "\n\nUse the following verified barangay knowledge base context if relevant. When context includes both ENGLISH and TAGALOG sections, select the appropriate language (Tagalog if detected). Cite only facts present there when possible.\n".$kbContext;
}

$messages = [
    [ 'role' => 'system', 'content' => $systemBase ],
    [ 'role' => 'user', 'content' => $userMessage ]
];

$data = [
    "model" => "meta-llama/llama-3-8b-instruct", // Replace with other model if needed
    "messages" => $messages,
    "temperature" => 0.5,
    "max_tokens" => 500
];

// Build a Referer from current host to satisfy OpenRouter identification for web apps
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$referer = getenv('OPENROUTER_APP_REFERER') ?: ($scheme . '://' . $host . '/');
$appTitle = getenv('OPENROUTER_APP_TITLE') ?: 'BPAMIS Case Assistant';

$headers = [
    "Content-Type: application/json",
    "Accept: application/json",
    "Authorization: Bearer $apiKey",
    // Identification headers for OpenRouter web apps
    "Referer: $referer",
    "X-Title: $appTitle",
    // Helpful explicit UA
    "User-Agent: BPAMIS/1.0 (+$referer)"
];

// function to perform the cURL request
$performRequest = function() use ($data, $headers) {
    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return [$response, $info, $err];
};

[$response, $info, $err] = $performRequest();
$status = $info['http_code'] ?? 0;
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "log.txt", sprintf("[%s] HTTP %s — %s\n", date('c'), (string)$status, is_string($response) ? trim($response) : '[no body]'), FILE_APPEND);

if ($err) {
    echo json_encode(["reply" => "Curl Error: $err"]);
    exit;
}

$responseData = json_decode($response, true);

// Retry once on 401 User not found (transient identification glitch)
if (($status === 401 || ($responseData['error']['code'] ?? null) === 401) && stripos(($responseData['error']['message'] ?? ''), 'user not found') !== false) {
    // short backoff
    usleep(250 * 1000);
    [$response, $info, $err] = $performRequest();
    $status = $info['http_code'] ?? 0;
    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "log.txt", sprintf("[%s] RETRY HTTP %s — %s\n", date('c'), (string)$status, is_string($response) ? trim($response) : '[no body]'), FILE_APPEND);
    if ($err) {
        echo json_encode(["reply" => "Curl Error (after retry): $err"]);
        exit;
    }
    $responseData = json_decode($response, true);
}

if (!is_array($responseData)) {
    echo json_encode(["reply" => "OpenRouter Error: Unexpected response from API (HTTP $status). Please try again later."]);
    exit;
}

if (isset($responseData['error'])) {
    $msg = $responseData['error']['message'] ?? 'Unknown error';
    $code = $responseData['error']['code'] ?? $status;
    echo json_encode(["reply" => "OpenRouter Error ($code): $msg"]);
    exit;
}

$botReply = $responseData["choices"][0]["message"]["content"] ?? null;
if (!$botReply) {
    echo json_encode(["reply" => "Sorry, I couldn’t generate a response right now. Please try again."]);
    exit;
}

// Escape HTML and convert newlines to <br> for readability
$botReply = nl2br(htmlspecialchars($botReply));

echo json_encode(["reply" => $botReply]);
