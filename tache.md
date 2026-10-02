<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();
include('configSite/config.php');
include 'includes/db_inc.php';
include ('manager/config/conf.php');
define("_LANG_FOLDER_", "fr");
define("_LANG_CNTNT_", "fr");

// ════════════════════════════════════════════════════════════════════════════
// EXTERNAL FLASK API
// ════════════════════════════════════════════════════════════════════════════

/**
 * Return MIME type without requiring mime_content_type().
 * This works on hosting environments where Fileinfo is unavailable.
 */
function getFileMimeType($path)
{
    $extension = strtolower(
        pathinfo($path, PATHINFO_EXTENSION)
    );

    switch ($extension) {
        case 'pdf':
            return 'application/pdf';

        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';

        case 'png':
            return 'image/png';

        default:
            return 'application/octet-stream';
    }
}


/**
 * Send the invoice and its documents to the external Flask API.
 */
function sendInvoiceToExternalApi(
    array $invoiceData,
    array $uploadedDocs,
    array $uploadedBcDocs
): array {

    // ========================================================
    // CHANGE THESE TWO VALUES
    // ========================================================

    $apiUrl = 'https://commerce-vente.jbelannour.org/api/factures';

    $apiKey = 'CHANGE_ME_SECRET_KEY';


    // ========================================================
    // CURL
    // ========================================================

    $curl = curl_init();

    if ($curl === false) {

        return [
            'success' => false,
            'error'   => 'Impossible d\'initialiser cURL.'
        ];
    }


    // ========================================================
    // DATA JSON
    // ========================================================

    $jsonData = json_encode(
        $invoiceData,
        JSON_UNESCAPED_UNICODE
    );

    if ($jsonData === false) {

        curl_close($curl);

        return [
            'success' => false,
            'error'   => 'Erreur JSON : ' . json_last_error_msg()
        ];
    }


    $postFields = [];

    $postFields['data'] = $jsonData;


    // ========================================================
    // LOCAL UPLOAD DIRECTORY
    // ========================================================

    $uploadDir = __DIR__ . '/uploads/factures/';


    // ========================================================
    // FACTURE PDF
    // ========================================================

    if (!empty($uploadedDocs['doc_facture_pdf'])) {

        $path = $uploadDir
              . $uploadedDocs['doc_facture_pdf'];

        if (is_file($path) && is_readable($path)) {

            $postFields['doc_facture_pdf'] = new CURLFile(
                $path,
                getFileMimeType($path),
                basename($path)
            );
        }
        else {

            error_log(
                '[reclamation.php] API: facture file not found: '
                . $path
            );
        }
    }


    // ========================================================
    // ATTESTATION FISCALE
    // ========================================================

    if (!empty($uploadedDocs['doc_attestation_fisc'])) {

        $path = $uploadDir
              . $uploadedDocs['doc_attestation_fisc'];

        if (is_file($path) && is_readable($path)) {

            $postFields['doc_attestation_fisc'] = new CURLFile(
                $path,
                getFileMimeType($path),
                basename($path)
            );
        }
        else {

            error_log(
                '[reclamation.php] API: attestation file not found: '
                . $path
            );
        }
    }


    // ========================================================
    // RIB
    // ========================================================

    if (!empty($uploadedDocs['doc_rib'])) {

        $path = $uploadDir
              . $uploadedDocs['doc_rib'];

        if (is_file($path) && is_readable($path)) {

            $postFields['doc_rib'] = new CURLFile(
                $path,
                getFileMimeType($path),
                basename($path)
            );
        }
        else {

            error_log(
                '[reclamation.php] API: RIB file not found: '
                . $path
            );
        }
    }


    // ========================================================
    // BONS DE COMMANDE
    // MULTIPLE FILES
    // ========================================================

    $bcIndex = 0;

    foreach ($uploadedBcDocs as $docName) {

        if (empty($docName)) {
            continue;
        }

        $path = $uploadDir . $docName;

        if (!is_file($path) || !is_readable($path)) {

            error_log(
                '[reclamation.php] API: BC file not found: '
                . $path
            );

            continue;
        }

        $postFields[
            "doc_bon_commande[$bcIndex]"
        ] = new CURLFile(
            $path,
            getFileMimeType($path),
            basename($path)
        );

        $bcIndex++;
    }


    // ========================================================
    // CURL REQUEST
    // ========================================================

    curl_setopt_array($curl, [

        CURLOPT_URL => $apiUrl,

        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS => $postFields,

        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $apiKey
        ],

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 180,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2
    ]);


    $response = curl_exec($curl);

    $curlError = curl_error($curl);

    $httpCode = curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);


    // ========================================================
    // CURL ERROR
    // ========================================================

    if ($response === false) {

        return [
            'success'   => false,
            'error'     => $curlError,
            'http_code' => $httpCode
        ];
    }


    // ========================================================
    // HTTP ERROR
    // ========================================================

    $decodedResponse = json_decode(
        $response,
        true
    );


    if ($httpCode < 200 || $httpCode >= 300) {

        return [
            'success'   => false,
            'error'     => is_array($decodedResponse)
                ? ($decodedResponse['error'] ?? $response)
                : $response,
            'http_code' => $httpCode,
            'response'  => $decodedResponse
        ];
    }


    // ========================================================
    // SUCCESS
    // ========================================================

    return [
        'success'   => true,
        'http_code' => $httpCode,
        'response'  => $decodedResponse
    ];
}
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_factures') {

    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');

    // — Clé API (à définir dans configSite/config.php)
    $API_KEY = defined('_API_KEY_') ? _API_KEY_ : '';
    // Clé de signature des liens de téléchargement (à définir en config)
    $DOWNLOAD_SECRET = defined('_DOWNLOAD_SIGNING_SECRET_')
        ? _DOWNLOAD_SIGNING_SECRET_
        : hash('sha256', __FILE__ . '|' . __DIR__ . '|download-sign');

    $respond = function(int $code, array $data): void {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    };

    // Vérification clé
    $apiKey = trim($_GET['api_key'] ?? '');
    if ($apiKey === '' || $apiKey !== $API_KEY) {
        $respond(401, ['success' => false, 'error' => 'Clé API manquante ou invalide.']);
    }

    $db  = $connectDatabase;

    $esc = function ($v) use ($db) {
        return mysqli_real_escape_string(
            $db,
            (string)$v
        );
    };

    // Paramètres de filtre
    $id     = isset($_GET['id'])     ? (int)$_GET['id']  : null;
    $depuis = trim($_GET['depuis']   ?? '');   // YYYY-MM-DD
    $jusqu  = trim($_GET['jusqu']    ?? '');   // YYYY-MM-DD
    $limit  = min((int)($_GET['limit'] ?? 50), 200);
    $page   = max((int)($_GET['page']  ?? 1), 1);
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    if ($id !== null) $where[] = "id = " . (int)$id;
    if ($depuis !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $depuis))
        $where[] = "date_facture >= '" . $esc($depuis) . "'";
    if ($jusqu !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $jusqu))
        $where[] = "date_facture <= '" . $esc($jusqu) . "'";

    $whereSQL = implode(' AND ', $where);

    $countRes = mysqli_query($db, "SELECT COUNT(*) AS total FROM apm_depot_facture WHERE {$whereSQL}");
    $total    = $countRes ? (int)mysqli_fetch_assoc($countRes)['total'] : 0;

    $res = mysqli_query($db,
        "SELECT id, numero_facture, date_facture, ice_fournisseur, numero_bc, numero_bl, date_bl, objet_facture,
                type_facture, type_document,
                montant_ht, montant_tva, montant_ttc, devise,
                conditions_paiement, date_echeance,
                nature_operation, nature_autres_detail,
                doc_facture_pdf, doc_bon_commande, doc_attestation_fisc, doc_rib,
                notes, declaration_acceptee, created_at
         FROM apm_depot_facture
         WHERE {$whereSQL}
         ORDER BY created_at DESC
         LIMIT {$limit} OFFSET {$offset}");

    if ($res === false) {
        $respond(500, ['success' => false, 'error' => 'Erreur requête : ' . mysqli_error($db)]);
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $baseUrl  = ($isHttps ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $factures = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['montant_ht']           = (float)$row['montant_ht'];
        $row['montant_tva']          = (float)$row['montant_tva'];
        $row['montant_ttc']          = (float)$row['montant_ttc'];
        $row['declaration_acceptee'] = (bool)$row['declaration_acceptee'];
        foreach (['doc_facture_pdf','doc_attestation_fisc','doc_rib'] as $f) {
            if (!empty($row[$f])) {
                $filename = basename((string)$row[$f]);
                $exp      = time() + 300; // 5 minutes
                $payload  = $filename . '|' . $exp;
                $sig      = hash_hmac('sha256', $payload, $DOWNLOAD_SECRET);

                $row[$f . '_url'] = $baseUrl
                    . '/uploads/factures/' . rawurlencode($filename)
                    . '?exp=' . $exp
                    . '&sig=' . $sig;
            } else {
                $row[$f . '_url'] = null;
            }
        }
        // doc_bon_commande peut contenir un JSON de plusieurs fichiers
        if (!empty($row['doc_bon_commande'])) {
            $bcFiles = json_decode($row['doc_bon_commande'], true);
            if (!is_array($bcFiles)) {
                $bcFiles = [$row['doc_bon_commande']];
            }
            $bcUrls = [];
            foreach ($bcFiles as $bcFile) {
                $filename = basename((string)$bcFile);
                $exp      = time() + 300;
                $payload  = $filename . '|' . $exp;
                $sig      = hash_hmac('sha256', $payload, $DOWNLOAD_SECRET);
                $bcUrls[] = $baseUrl
                    . '/uploads/factures/' . rawurlencode($filename)
                    . '?exp=' . $exp
                    . '&sig=' . $sig;
            }
            $row['doc_bon_commande_url'] = count($bcUrls) === 1 ? $bcUrls[0] : $bcUrls;
        } else {
            $row['doc_bon_commande_url'] = null;
        }
        $factures[] = $row;
    }
    mysqli_free_result($res);

    $respond(200, [
        'success'  => true,
        'total'    => $total,
        'page'     => $page,
        'limit'    => $limit,
        'pages'    => (int)ceil($total / $limit),
        'factures' => $factures,
    ]);
}
// ════════════════════════════════════════════════════════════════════════════
// FIN API GET
// ════════════════════════════════════════════════════════════════════════════

// ════════════════════════════════════════════════════════════════════════════
// API GET sécurisé — téléchargement document facture
// URL attendue : /uploads/factures/<fichier>?exp=...&sig=...
// (avec réécriture serveur vers reclamation.php?ajax=download_facture&file=<fichier>)
// ════════════════════════════════════════════════════════════════════════════
if (isset($_GET['ajax']) && $_GET['ajax'] === 'download_facture') {
    $DOWNLOAD_SECRET = defined('_DOWNLOAD_SIGNING_SECRET_')
        ? _DOWNLOAD_SIGNING_SECRET_
        : hash('sha256', __FILE__ . '|' . __DIR__ . '|download-sign');

    $forbidden = function(string $msg = 'Accès refusé'): void {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $msg;
        exit;
    };

    $notFound = function(): void {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Fichier introuvable';
        exit;
    };

    $requested = (string)($_GET['file'] ?? '');
    $filename  = basename(rawurldecode($requested));
    $exp       = isset($_GET['exp']) ? (int)$_GET['exp'] : 0;
    $sig       = (string)($_GET['sig'] ?? '');

    // Validation stricte du nom de fichier
    if ($filename === '' || $filename !== $requested) {
        $forbidden();
    }
    if (!preg_match('/^[A-Za-z0-9._-]+\.(pdf|jpe?g|png)$/i', $filename)) {
        $forbidden();
    }

    // Vérification expiration du lien
    if ($exp <= 0 || time() > $exp) {
        $forbidden('Lien expiré');
    }

    // Vérification signature HMAC
    $payload  = $filename . '|' . $exp;
    $expected = hash_hmac('sha256', $payload, $DOWNLOAD_SECRET);
    if (!hash_equals($expected, $sig)) {
        $forbidden();
    }

    $baseDir = realpath(__DIR__ . '/uploads/factures');
    if ($baseDir === false) {
        $notFound();
    }

    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $filename);
    if ($filePath === false) {
        $notFound();
    }

    // Anti path traversal
    if (strpos($filePath, $baseDir . DIRECTORY_SEPARATOR) !== 0) {
        $forbidden();
    }

    if (!is_file($filePath) || !is_readable($filePath)) {
        $notFound();
    }

    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $detected = finfo_file($fi, $filePath);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
            finfo_close($fi);
        }
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($filename)) . '"');
    header('Content-Length: ' . (string)filesize($filePath));
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($filePath);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$success    = false;
$userErrors = [];
$dbError    = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $numero_facture       = trim($_POST["numero_facture"]       ?? '');
    $date_facture         = trim($_POST["date_facture"]         ?? '');
    $ice_fournisseur      = trim($_POST["ice_fournisseur"]      ?? '');
    // Bons de commande multiples
    $bons_commande_raw    = $_POST["numero_bc"] ?? [];
    if (!is_array($bons_commande_raw)) $bons_commande_raw = [$bons_commande_raw];
    $bons_commande        = array_values(array_filter(array_map('trim', $bons_commande_raw)));
    $numero_bc            = implode(', ', $bons_commande); // valeur consolidée pour BDD/email
    // Bons de livraison multiples, avec une date commune
    $bons_livraison_raw   = $_POST["numero_bl"] ?? [];
    if (!is_array($bons_livraison_raw)) $bons_livraison_raw = [$bons_livraison_raw];
    $bons_livraison       = array_values(array_filter(array_map('trim', $bons_livraison_raw)));
    $numero_bl            = implode(', ', $bons_livraison);
    $date_bl              = trim($_POST["date_bl"]              ?? '');
    $objet_facture        = trim($_POST["objet_facture"]        ?? '');
    $type_facture         = trim($_POST["type_facture"]         ?? '');
    $type_document        = trim($_POST["type_document"]        ?? '');
    $montant_ht           = trim($_POST["montant_ht"]           ?? '0');
    $montant_tva          = trim($_POST["montant_tva"]          ?? '0');
    $montant_ttc          = trim($_POST["montant_ttc"]          ?? '0');
    $devise               = trim($_POST["devise"]               ?? 'MAD');
    $conditions_paiement  = trim($_POST["conditions_paiement"]  ?? '');
    $date_echeance        = trim($_POST["date_echeance"]        ?? '');
    $nature_operation     = trim($_POST["nature_operation"]     ?? 'autres');
    $nature_autres_detail = trim($_POST["nature_autres_detail"] ?? '');
    $notes                = trim($_POST["notes_fournisseur"]    ?? '');
    $declaration_acceptee = isset($_POST["declaration_acceptee"]) ? 1 : 0;

    if ($numero_facture === '')  $userErrors[] = "Veuillez renseigner le numéro de facture.";
    if (empty($bons_commande))    $userErrors[] = "Veuillez renseigner au moins un numéro de bon de commande.";
    if ($date_facture === '')    $userErrors[] = "Veuillez renseigner la date de facture.";
    if ($ice_fournisseur === '') $userErrors[] = "Veuillez renseigner le numéro ICE du fournisseur.";
    if (empty($bons_livraison))   $userErrors[] = "Veuillez renseigner au moins un numéro de bon de livraison.";
    if ($date_bl === '')         $userErrors[] = "Veuillez renseigner la date du bon de livraison.";
    if ($type_facture !== 'facture' && $type_facture !== 'avoir') {
        $userErrors[] = "Veuillez préciser s'il s'agit d'une facture ou d'une facture avoir.";
    }
    if ($type_document !== 'copie' && $type_document !== 'original') {
        $userErrors[] = "Veuillez préciser si le document est une copie ou un original.";
    }
    if ($declaration_acceptee !== 1) $userErrors[] = "Veuillez accepter la déclaration pour soumettre votre dépôt.";

    // ── Upload documents ─────────────────────────────────────────────────────
    $docFields = [
        'doc_facture_pdf'      => ['pdf'],
        'doc_attestation_fisc' => ['pdf', 'jpg', 'jpeg', 'png'],
        'doc_rib'              => ['pdf', 'jpg', 'jpeg', 'png'],
    ];
    $uploadedDocs    = [];
    $uploadedBcDocs  = []; // tableau des PDFs bons de commande indexés

    if (empty($userErrors)) {
        $docUploadDir = __DIR__ . '/uploads/factures/';
        if (!is_dir($docUploadDir)) {
            if (!mkdir($docUploadDir, 0755, true)) {
                $userErrors[] = "Impossible de créer le dossier des documents.";
            }
        }
        if (empty($userErrors)) {
            if (!is_writable($docUploadDir)) {
                $userErrors[] = "Le dossier de téléchargement des documents n'est pas accessible.";
            } else {
                foreach ($docFields as $field => $allowedExtsList) {
                    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
                    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                        $userErrors[] = "Erreur téléchargement {$field}. Veuillez réessayer.";
                        continue;
                    }
                    $fileExt = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    if (!in_array($fileExt, $allowedExtsList)) {
                        $userErrors[] = "Format non autorisé pour {$field} (PDF, JPG ou PNG attendu).";
                        continue;
                    }
                    if ($_FILES[$field]['size'] > 10 * 1024 * 1024) {
                        $userErrors[] = "{$field} trop volumineux (max 10 MB).";
                        continue;
                    }
                    $ext     = ($fileExt === 'jpeg') ? 'jpg' : $fileExt;
                    $docName = $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES[$field]['tmp_name'], $docUploadDir . $docName)) {
                        $uploadedDocs[$field] = $docName;
                    } else {
                        $userErrors[] = "Erreur téléchargement {$field}.";
                    }
                }

                // ── Upload PDFs bons de commande (tableau) ──────────────────
                if (isset($_FILES['doc_bon_commande']) && is_array($_FILES['doc_bon_commande']['name'])) {
                    $bcAllowed = ['pdf', 'jpg', 'jpeg', 'png'];
                    foreach ($_FILES['doc_bon_commande']['name'] as $idx => $bcName) {
                        if ($_FILES['doc_bon_commande']['error'][$idx] === UPLOAD_ERR_NO_FILE) continue;
                        if ($_FILES['doc_bon_commande']['error'][$idx] !== UPLOAD_ERR_OK) {
                            $userErrors[] = "Erreur téléchargement bon de commande #" . ($idx+1) . ". Veuillez réessayer.";
                            continue;
                        }
                        $fileExt = strtolower(pathinfo($bcName, PATHINFO_EXTENSION));
                        if (!in_array($fileExt, $bcAllowed)) {
                            $userErrors[] = "Format non autorisé pour le bon de commande #" . ($idx+1) . " (PDF, JPG ou PNG attendu).";
                            continue;
                        }
                        if ($_FILES['doc_bon_commande']['size'][$idx] > 10 * 1024 * 1024) {
                            $userErrors[] = "Bon de commande #" . ($idx+1) . " trop volumineux (max 10 MB).";
                            continue;
                        }
                        $ext     = ($fileExt === 'jpeg') ? 'jpg' : $fileExt;
                        $docName = 'doc_bon_commande_' . $idx . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (move_uploaded_file($_FILES['doc_bon_commande']['tmp_name'][$idx], $docUploadDir . $docName)) {
                            $uploadedBcDocs[$idx] = $docName;
                        } else {
                            $userErrors[] = "Erreur téléchargement bon de commande #" . ($idx+1) . ".";
                        }
                    }
                }
                // Stocker tous les documents BC dans la colonne existante
                if (!empty($uploadedBcDocs)) {
                    $uploadedDocs['doc_bon_commande'] = json_encode(
                        array_values($uploadedBcDocs),
                        JSON_UNESCAPED_UNICODE
                    );
                }
            }
        }
    }

    // ── Enregistrement BDD ───────────────────────────────────────────────────
    // ── Enregistrement BDD ───────────────────────────────────────────────────

    if (empty($userErrors)) {
    
        $db  = $connectDatabase;
    
        $esc = function ($v) use ($db) {
            return mysqli_real_escape_string(
                $db,
                (string)$v
            );
        };
        $val = function ($v) use ($esc) {
            return ($v === '' || $v === null)
        ? 'NULL'
            : "'" . $esc($v) . "'";
        };
        
        $num = function ($v) {
            return is_numeric($v)
                ? (float)$v
                : 0.00;
        };
    
        mysqli_begin_transaction($db);
    
        try {
    
            // ====================================================
            // INSERT MYSQL
            // ====================================================
    
            $ins = mysqli_query(
                $db,
    
                "INSERT INTO apm_depot_facture
                    (
                        numero_facture,
                        date_facture,
                        ice_fournisseur,
                        numero_bc,
                        numero_bl,
                        date_bl,
                        objet_facture,
                        type_facture,
                        type_document,
                        montant_ht,
                        montant_tva,
                        montant_ttc,
                        devise,
                        conditions_paiement,
                        date_echeance,
                        nature_operation,
                        nature_autres_detail,
                        doc_facture_pdf,
                        doc_bon_commande,
                        doc_attestation_fisc,
                        doc_rib,
                        notes,
                        declaration_acceptee
                    )
    
                 VALUES
                    (
                        '"
                . $esc($numero_facture)
                . "','"
                . $esc($date_facture)
                . "',"
                . $val($ice_fournisseur)
                . ","
                . $val($numero_bc)
                . ","
                . $val($numero_bl)
                . ","
                . $val($date_bl)
                . ","
                . $val($objet_facture)
                . ","
                . $val($type_facture)
                . ","
                . $val($type_document)
                . ","
                . $num($montant_ht)
                . ","
                . $num($montant_tva)
                . ","
                . $num($montant_ttc)
                . ",'"
                . $esc($devise ?: 'MAD')
                . "',"
                . $val($conditions_paiement)
                . ","
                . $val($date_echeance)
                . ",'"
                . $esc($nature_operation ?: 'autres')
                . "',"
                . $val($nature_autres_detail)
                . ","
                . $val(
                    $uploadedDocs['doc_facture_pdf'] ?? ''
                )
                . ","
                . $val(
                    $uploadedDocs['doc_bon_commande'] ?? ''
                )
                . ","
                . $val(
                    $uploadedDocs['doc_attestation_fisc'] ?? ''
                )
                . ","
                . $val(
                    $uploadedDocs['doc_rib'] ?? ''
                )
                . ","
                . $val($notes)
                . ","
                . (int)$declaration_acceptee
                . ")"
            );
    
    
            // ====================================================
            // INSERT ERROR
            // ====================================================
    
            if ($ins === false) {
    
                throw new Exception(
                    "INSERT facture : "
                    . mysqli_error($db)
                );
            }
    
    
            // ====================================================
            // COMMIT MYSQL
            // ====================================================
    
            mysqli_commit($db);
    
    
        } catch (Exception $e) {
    
            // ====================================================
            // ROLLBACK ONLY MYSQL ERRORS
            // ====================================================
    
            mysqli_rollback($db);
    
            $msg = $e->getMessage();
    
    
            $isDuplicate =
                strpos($msg, '1062') !== false
                ||
                stripos(
                    $msg,
                    'Duplicate entry'
                ) !== false;
    
    
            if ($isDuplicate) {
    
                $userErrors[] =
                    "Ce numéro de facture ("
                    . htmlspecialchars(
                        $numero_facture,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . ") existe déjà. "
                    . "Veuillez vérifier le numéro saisi.";
    
            } else {
    
                error_log(
                    '[reclamation.php] DB Error: '
                    . $msg
                );
    
                // Temporary useful error
                // Remove detailed message later if desired.
                $userErrors[] =
                    "Erreur lors de l'enregistrement : "
                    . htmlspecialchars(
                        $msg,
                        ENT_QUOTES,
                        'UTF-8'
                    );
    
                $dbError = true;
            }
        }
    }
    // ════════════════════════════════════════════════════════════════════════════
    // ENVOI VERS API FLASK EXTERNE
    // ════════════════════════════════════════════════════════════════════════════
    //
    // IMPORTANT:
    // This is OUTSIDE the MySQL transaction.
    //
    // Therefore:
    // MySQL succeeds → COMMIT
    // then → Flask API
    //
    // If Flask fails, the MySQL invoice remains saved.
    // ════════════════════════════════════════════════════════════════════════════
    
    $externalApiResult = null;
    
    
    if (empty($userErrors) && !$dbError) {
    
    
        // ========================================================
        // DATA TO SEND
        // ========================================================
    
        $externalApiData = [
    
            'numero_facture' =>
                $numero_facture,
    
            'date_facture' =>
                $date_facture,
    
            'ice_fournisseur' =>
                $ice_fournisseur,
    
            'numero_bc' =>
                $numero_bc,
    
            'numero_bl' =>
                $numero_bl,
    
            'date_bl' =>
                $date_bl,
    
            'objet_facture' =>
                $objet_facture,
    
            'type_facture' =>
                $type_facture,
    
            'type_document' =>
                $type_document,
    
            'montant_ht' =>
                is_numeric($montant_ht)
                    ? (float)$montant_ht
                    : 0,
    
            'montant_tva' =>
                is_numeric($montant_tva)
                    ? (float)$montant_tva
                    : 0,
    
            'montant_ttc' =>
                is_numeric($montant_ttc)
                    ? (float)$montant_ttc
                    : 0,
    
            'devise' =>
                $devise ?: 'MAD',
    
            'conditions_paiement' =>
                $conditions_paiement,
    
            'date_echeance' =>
                $date_echeance,
    
            'nature_operation' =>
                $nature_operation ?: 'autres',
    
            'nature_autres_detail' =>
                $nature_autres_detail,
    
            'notes' =>
                $notes,
    
            'declaration_acceptee' =>
                (bool)$declaration_acceptee
        ];
    
    
        // ========================================================
        // SEND
        // ========================================================
    
        try {
    
            $externalApiResult =
                sendInvoiceToExternalApi(
                    $externalApiData,
                    $uploadedDocs,
                    $uploadedBcDocs
                );
    
    
            // ====================================================
            // API FAILED
            // ====================================================
    
            if (
                !isset(
                    $externalApiResult['success']
                )
                ||
                !$externalApiResult['success']
            ) {
    
                error_log(
                    '[reclamation.php] '
                    . 'External API failed: '
                    . json_encode(
                        $externalApiResult,
                        JSON_UNESCAPED_UNICODE
                    )
                );
    
                // IMPORTANT:
                // DO NOT rollback MySQL.
                //
                // The invoice has already been committed.
            }
    
    
            // ====================================================
            // API SUCCESS
            // ====================================================
    
            else {
    
                error_log(
                    '[reclamation.php] '
                    . 'External API success: '
                    . json_encode(
                        $externalApiResult,
                        JSON_UNESCAPED_UNICODE
                    )
                );
            }
    
    
        } catch (Throwable $apiException) {
    
            // ====================================================
            // API EXCEPTION
            // ====================================================
            //
            // DO NOT rollback MySQL.
            // MySQL is already committed.
            // ====================================================
    
            error_log(
                '[reclamation.php] '
                . 'External API exception: '
                . $apiException->getMessage()
            );
    
            $externalApiResult = [
    
                'success' => false,
    
                'error' =>
                    $apiException->getMessage()
            ];
        }
    }
    // ── Envoi email ──────────────────────────────────────────────────────────
    if (empty($userErrors) && !$dbError) {
        $to = defined('_MAIL_RECLAMATION_') 
        ? _MAIL_RECLAMATION_ 
        : "abdessamad.mzn@gmail.com, archiviste.4@jbel-annour.ma, archiviste.6@jbel-annour.ma, responsable.archive@jbel-annour.ma, badrbinoua07@gmail.com";
        $typeFactureLabel = ($type_facture === 'avoir') ? 'Facture avoir' : 'Facture';
        $subject = "Nouveau dépôt de {$typeFactureLabel} — N° {$numero_facture}";
        
        $e = function ($v) {
            return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        };
        $htmlBody = '<!DOCTYPE html><html><body style="margin:0;padding:0;background-color:#f6f8fb;">'
            . '<div style="max-width:700px;margin:24px auto;font-family:Arial,Helvetica,sans-serif;color:#202124;">'
            . '<div style="background:#1f3b64;color:#ffffff;padding:16px 20px;border-radius:8px 8px 0 0;">'
            . '<h2 style="margin:0;font-size:20px;">Nouveau depot de ' . $e(strtolower($typeFactureLabel)) . '</h2>'
            . '<p style="margin:8px 0 0;font-size:13px;opacity:0.9;">Notification automatique</p>'
            . '</div>'
            . '<div style="background:#ffffff;border:1px solid #e3e6ec;border-top:0;padding:20px;border-radius:0 0 8px 8px;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
            . '<tr><td style="padding:8px 0;width:210px;color:#5f6368;"><strong>Type</strong></td><td style="padding:8px 0;">' . $e($typeFactureLabel) . '</td></tr>'
            . '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Numero facture</strong></td><td style="padding:8px 0;">' . $e($numero_facture) . '</td></tr>'
            . '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Date facture</strong></td><td style="padding:8px 0;">' . $e($date_facture) . '</td></tr>';
        if ($ice_fournisseur !== '') {
            $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;"><strong>ICE Fournisseur</strong></td><td style="padding:8px 0;">' . $e($ice_fournisseur) . '</td></tr>';
        }
        if ($numero_bc !== '') {
            $bcList = implode(', ', array_map(function ($b) use ($e) {
                return $e($b);
            }, $bons_commande ?: [$numero_bc]));
            $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Bon(s) de commande</strong></td><td style="padding:8px 0;">' . $bcList . '</td></tr>';
        }
        if ($numero_bl !== '') {
            $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Numero Bon Livraison</strong></td><td style="padding:8px 0;">' . $e($numero_bl) . '</td></tr>';
        }
        if ($date_bl !== '') {
            $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Date Bon Livraison</strong></td><td style="padding:8px 0;">' . $e($date_bl) . '</td></tr>';
        }
        if ($objet_facture !== '') {
            $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Objet</strong></td><td style="padding:8px 0;">' . $e($objet_facture) . '</td></tr>';
        }
        $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Type de document</strong></td><td style="padding:8px 0;text-transform:capitalize;">' . $e($type_document) . '</td></tr>';
        $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;"><strong>Montant TTC</strong></td><td style="padding:8px 0;">' . $e($montant_ttc) . ' ' . $e($devise) . '</td></tr>';
        if ($notes !== '') {
            $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;vertical-align:top;"><strong>Notes</strong></td><td style="padding:8px 0;">' . nl2br($e($notes)) . '</td></tr>';
        }
        if (!empty($uploadedDocs)) {
            $htmlBody .= '<tr><td style="padding:8px 0;color:#5f6368;vertical-align:top;"><strong>Documents joints</strong></td><td style="padding:8px 0;">' . $e(implode(', ', array_keys($uploadedDocs))) . '</td></tr>';
        }
        $htmlBody .= '</table>'
            . '<p style="margin:18px 0 0;color:#5f6368;font-size:12px;">Merci de verifier ce depot dans votre systeme.</p>'
            . '</div></div></body></html>';

        $attachments = [];
        foreach (['doc_facture_pdf', 'doc_attestation_fisc', 'doc_rib'] as $docKey) {
            if (!empty($uploadedDocs[$docKey])) {
                $docPath = __DIR__ . '/uploads/factures/' . $uploadedDocs[$docKey];
                if (is_file($docPath) && is_readable($docPath)) {
                    $attachments[] = $docPath;
                }
            }
        }
        foreach ($uploadedBcDocs as $docName) {
            $docPath = __DIR__ . '/uploads/factures/' . $docName;
            if (is_file($docPath) && is_readable($docPath)) $attachments[] = $docPath;
        }

        if (!empty($attachments)) {
            $boundary = '==Multipart_Boundary_x' . md5((string)microtime(true)) . 'x';
            $headers  = "From: no-reply@jbelannourmaroc.ma\r\n";
            $headers .= "Cc: Jbel-annour@jbel-annour.com\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $mimeBody  = "--{$boundary}\r\n";
            $mimeBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $mimeBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $mimeBody .= $htmlBody . "\r\n";
            foreach ($attachments as $ap) {
                $fc = @file_get_contents($ap); if ($fc === false) continue;
                $fn = str_replace(["\r","\n","\""],'_',basename($ap));
                $mt = 'application/octet-stream';
                if (function_exists('finfo_open')) {
                    $fi = finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi) { $d = finfo_file($fi,$ap); if (is_string($d)&&$d!=='') $mt=$d; finfo_close($fi); }
                }
                $mimeBody .= "--{$boundary}\r\nContent-Type: {$mt}; name=\"{$fn}\"\r\nContent-Disposition: attachment; filename=\"{$fn}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($fc)) . "\r\n";
            }
            $mimeBody .= "--{$boundary}--\r\n";
            $sent = mail($to, $subject, $mimeBody, $headers);
        } else {
            $headers  = "From: no-reply@jbelannourmaroc.ma\r\n";
            $headers .= "Cc: Jbel-annour@jbel-annour.com\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $sent     = mail($to, $subject, $htmlBody, $headers);
        }
        if (!$sent) error_log('[reclamation.php] mail() failed to: ' . $to);

        $success = true;
        $_POST   = [];
    }
}

$v = function ($k) {return htmlspecialchars($_POST[$k] ?? '', ENT_QUOTES, 'UTF-8');};
?>
<!DOCTYPE html>
<html lang="<?php echo _LANG_FOLDER_; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Dépôt de facture - Briqueterie Jbel Annour" />
    <link rel="icon" href="<?php echo _SITE_URL_; ?>/assets/images/favicon.png" sizes="35x35" type="image/png">
    <title>Dépôt de facture - Briqueterie Jbel Annour</title>

    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/all.min.css">
    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/flaticon.css">
    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/animate.min.css">
    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/jquery.fancybox.min.css">
    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/slick.css">
    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo _SITE_URL_; ?>/assets/css/responsive.css">
    <?php include('meta.php'); ?>

    <style>
        .stepper-nav { position: relative; }
        .stepper-nav::before,
        .stepper-nav::after {
            content: ''; position: absolute;
            top: 20px; left: 50px; right: 50px; height: 2px; z-index: 1;
        }
        .stepper-nav::before { background: #e0e0e0; }
        .stepper-nav::after  { background: var(--theme-color); transition: width .4s ease; width: 0; }
        .stepper-nav.step-1::after { width: 0%;   }
        .stepper-nav.step-2::after { width: 100%; }

        .step-item {
            display: flex; flex-direction: column; align-items: center;
            cursor: pointer; position: relative; z-index: 2;
            background: #fff; padding: 0 10px;
        }
        .step-number {
            width: 42px; height: 42px; border-radius: 50%;
            border: 2px solid #e0e0e0; background: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px; color: #999;
            transition: all .3s ease; margin-bottom: 8px;
        }
        .step-item.active    .step-number { background: var(--theme-color); border-color: var(--theme-color); color:#fff; transform:scale(1.1); }
        .step-item.completed .step-number { background: var(--color4);      border-color: var(--color4);      color:#fff; }
        .step-item.future    .step-number { opacity: .5; cursor: not-allowed; }
        .step-item:not(.future):hover .step-number { border-color: var(--theme-color); color: var(--theme-color); }
        .step-item.active:hover    .step-number,
        .step-item.completed:hover .step-number { color:#fff; transform:scale(1.05); }

        .step-label {
            font-size: 12px; font-weight: 500; color: #777;
            text-transform: uppercase; letter-spacing: .5px;
            text-align: center; max-width: 100px; line-height: 1.3;
            transition: color .3s ease;
        }
        .step-item.active    .step-label { color: var(--color1); font-weight: 600; }
        .step-item.completed .step-label { color: var(--color4); font-weight: 600; }

        .step-content { display: none; animation: fadeInUp .4s ease; }
        .step-content.active { display: block; }
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .recl-sep {
            display: flex; align-items: center; gap: .75rem;
            margin: 0 0 1.4rem; padding-bottom: .75rem;
            border-bottom: 2px solid var(--theme-color);
        }
        .recl-sep i     { color: var(--theme-color); font-size: 1.1rem; width: 2rem; text-align: center; }
        .recl-sep span  { font-size: 1rem; font-weight: 600; color: var(--color1); text-transform: uppercase; letter-spacing: .5px; }
        .recl-sep small { font-size: .75rem; font-weight: 400; color: #aaa; text-transform: none; margin-left: auto; }

        .recl-note {
            background-color: var(--color1); border-radius: 5px;
            padding: .9rem 1.25rem; display: flex; gap: .75rem; align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        .recl-note i        { color: var(--theme-color); margin-top: 2px; flex-shrink: 0; }
        .recl-note p        { margin:0; font-size:13px; color:rgba(255,255,255,.75); line-height:1.6; }
        .recl-note p strong { color: var(--theme-color); }

        .recl-check {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #efefef; border-radius: 5px;
            padding: .9rem 1.25rem; margin-bottom: 1.5rem; cursor: pointer;
        }
        .recl-check input[type="checkbox"] {
            width:16px; height:16px; flex-shrink:0;
            margin-top:2px; accent-color: var(--theme-color); cursor:pointer;
        }
        .recl-check label { font-size:14px; color:#777; cursor:pointer; margin:0; }

        .contact-form input[type="file"] {
            padding: 10px 1.25rem !important;
            border: 1px dashed var(--theme-color) !important;
            background-color: #efefef; cursor:pointer;
        }
        .contact-form select { height:53px !important; padding:0 1.25rem !important; }

        .invoice-type-field { margin-bottom: 1.5rem; }
        .invoice-type-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 5px;
        }
        .invoice-type-option {
            display: flex !important;
            align-items: center;
            gap: 10px;
            min-height: 53px;
            margin: 0 !important;
            padding: 0 16px;
            border: 1px solid #e3e6ec;
            border-radius: 5px;
            background: #f8f9fa;
            color: #555;
            font-weight: 400 !important;
            cursor: pointer;
            transition: border-color .2s ease, background-color .2s ease;
        }
        .invoice-type-option:hover {
            border-color: var(--theme-color);
            background: #fff;
        }
        .contact-form .invoice-type-option input[type="radio"] {
            float: none !important;
            flex: 0 0 18px;
            width: 18px !important;
            height: 18px !important;
            margin: 0 !important;
            padding: 0 !important;
            accent-color: var(--theme-color);
            cursor: pointer;
        }
        .invoice-type-option:has(input:checked) {
            border-color: var(--theme-color);
            background: #fff;
            color: var(--color1);
            box-shadow: 0 0 0 1px var(--theme-color);
        }

        .req { color: var(--theme-color); }
        .field-error { border-color:#dc3545 !important; background-color:#fff5f5 !important; }
        .error-msg   { color:#dc3545; font-size:12px; margin-top:5px; display:none; }
        .error-msg.show { display:block; }

        .step-indicator {
            text-align:center; margin-bottom:1.5rem;
            font-size:13px; color:var(--theme-color); font-weight:500;
            text-transform:uppercase; letter-spacing:1px;
        }
        .step-actions {
            display:flex; justify-content:space-between;
            margin-top:2rem; padding-top:1.5rem; border-top:1px solid #eee;
        }
        #bc-docs-list .bc-doc-row {
            background: #f8f9fa; border-radius: 6px;
            padding: 10px 12px; border: 1px solid #e3e6ec;
        }
        #bc-docs-list .bc-doc-row input[type="file"] { margin:0 !important; }

        #bc-list .bc-row input,
        #bl-list .bl-row input {
            width: auto !important;
            flex: 1 1 auto !important;
            margin-bottom: 0 !important;
        }
        #bc-list .bc-row,
        #bl-list .bl-row {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin-bottom: 8px;
        }
        @media (max-width:768px) {
            .step-item   { flex:1 1 30%; }
            .step-label  { font-size:10px; max-width:80px; }
            .step-number { width:35px; height:35px; font-size:12px; }
            .invoice-type-options { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main>
    <?php include('header.php'); ?>

    <section>
        <div class="w-100 pt-170 pb-150 dark-layer3 opc7 position-relative">
            <div class="fixed-bg" style="background-image:url(<?php echo _SITE_URL_; ?>/assets/images/pagetop-bg.jpg);"></div>
            <div class="container">
                <div class="page-top-wrap w-100">
                    <h1 class="mb-0">Dépôt de facture</h1>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo _SITE_URL_; ?>">Accueil</a></li>
                        <li class="breadcrumb-item active">Dépôt de facture</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="w-100 pt-60 pb-100">
            <div class="container">

                <div class="sec-title v2 w-100">
                    <div class="sec-title-inner d-inline-block">
                        <h2 class="mb-0">Dépôt de facture</h2>
                    </div>
                </div>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Votre facture a été enregistrée avec succès. Elle sera traitée après vérification de conformité.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($userErrors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong><i class="fas fa-exclamation-circle me-2"></i>Veuillez corriger les erreurs suivantes :</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($userErrors as $err): ?>
                        <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php endif; ?>

                <?php if ($dbError): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-database me-2"></i>
                    <strong>Erreur technique :</strong> Une erreur est survenue lors de l'enregistrement.
                    Veuillez réessayer ou contacter notre support si le problème persiste.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-10 col-md-12 mx-auto">
                        <div class="card shadow-sm p-4 mb-4">

                            <div class="stepper-nav step-1 d-flex justify-content-between position-relative mb-4 px-3" id="stepperNav">
                                <?php $steps = [1 => 'Facture', 2 => 'Documents'];
                                foreach ($steps as $n => $lbl): ?>
                                <div class="step-item <?php echo $n === 1 ? 'active' : 'future'; ?>"
                                     data-step="<?php echo $n; ?>"
                                     onclick="goToStep(<?php echo $n; ?>)">
                                    <div class="step-number"><?php echo str_pad($n, 2, '0', STR_PAD_LEFT); ?></div>
                                    <div class="step-label"><?php echo $lbl; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <form method="post" enctype="multipart/form-data" class="contact-form w-100" id="reclamationForm" novalidate>

                                <!-- ══ ÉTAPE 1 — Facture ══ -->
                                <div class="step-content active" data-step="1">
    <div class="step-indicator">Étape 1 sur 2 — Détails de la facture</div>

    <div class="recl-sep">
        <i class="fas fa-file-invoice"></i>
        <span>Informations facture</span>
        <small>* Champs obligatoires</small>
    </div>

    <!-- Type de facture : Facture / Facture avoir -->
    <div class="row">
        <div class="col-12 invoice-type-field">
            <label>Type de facture <span class="req">*</span></label>
            <div class="invoice-type-options">
                <label for="type_facture_normale" class="invoice-type-option">
                    <input type="radio"
                           id="type_facture_normale"
                           name="type_facture"
                           value="facture"
                           <?php echo (($_POST['type_facture'] ?? '') === 'facture') ? 'checked' : ''; ?>
                           required>
                    <span>Facture</span>
                </label>
                <label for="type_facture_avoir" class="invoice-type-option">
                    <input type="radio"
                           id="type_facture_avoir"
                           name="type_facture"
                           value="avoir"
                           <?php echo (isset($_POST['type_facture']) && $_POST['type_facture'] === 'avoir') ? 'checked' : ''; ?>
                           required>
                    <span>Facture avoir</span>
                </label>
            </div>
            <div class="error-msg" id="error_type_facture">Veuillez sélectionner le type de facture.</div>
        </div>
    </div>

    <!-- Row 1 : N° Facture + Date facture + ICE Fournisseur -->
    <div class="row">
        <div class="col-md-4">
            <label>N° Facture <span class="req">*</span></label>
            <input type="text" name="numero_facture" id="step1_numero"
                   placeholder="FAC-2025-XXXX"
                   required onblur="validateField(this)"
                   value="<?php echo $v('numero_facture'); ?>">
            <div class="error-msg">Veuillez renseigner le numéro de facture.</div>
        </div>
        <div class="col-md-4">
            <label>Date facture <span class="req">*</span></label>
            <input type="date" name="date_facture" id="step1_date"
                   required onblur="validateField(this)"
                   onkeydown="return false"
                   value="<?php echo $v('date_facture'); ?>">
            <div class="error-msg">Veuillez renseigner la date de facture.</div>
        </div>
        <div class="col-md-4">
            <label>ICE Fournisseur <span class="req">*</span></label>
            <input type="text" name="ice_fournisseur" id="step1_ice"
                   placeholder="Ex : 001234567890123"
                   required onblur="validateField(this)"
                   value="<?php echo $v('ice_fournisseur'); ?>">
            <div class="error-msg">Veuillez renseigner le numéro ICE du fournisseur.</div>
        </div>
    </div>

    <!-- Row 2 : N° Bon(s) de commande + N° Bon(s) de livraison -->
    <div class="row align-items-start">
        <div class="col-md-6">
            <label>N° Bon(s) de commande <span class="req">*</span></label>
            <div id="bc-list">
                <?php
                $prevBcList = isset($_POST['numero_bc']) ? (array)$_POST['numero_bc'] : [''];
                foreach ($prevBcList as $i => $bcVal):
                    $bcVal = htmlspecialchars($bcVal, ENT_QUOTES, 'UTF-8');
                ?>
                <div class="bc-row mb-2" data-index="<?php echo $i; ?>" style="display: flex; flex-direction: row; align-items: center; gap: 8px;">
                    <input type="text"
                           name="numero_bc[]"
                           placeholder="BC-XXXX"
                           value="<?php echo $bcVal; ?>"
                           class="mb-0"
                           required onblur="validateField(this)"
                           style="flex:1; height:45px; padding:0 15px;">
                    <?php if ($i === 0): ?>
                    <button type="button" class="btn btn-primary flex-shrink-0"
                            onclick="addBcRow()" title="Ajouter un bon de commande"
                            style="height:45px; width:45px; padding:0; border-radius:5px; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-plus"></i>
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-danger flex-shrink-0"
                            onclick="removeBcRow(this)" title="Supprimer"
                            style="height:45px; width:45px; padding:0; border-radius:5px; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="error-msg" id="error_bc">Veuillez renseigner au moins un bon de commande.</div>
        </div>

        <div class="col-md-6">
            <label>N° Bon(s) de livraison <span class="req">*</span></label>
            <div id="bl-list">
                <?php
                $prevBlList = isset($_POST['numero_bl']) ? (array)$_POST['numero_bl'] : [''];
                foreach ($prevBlList as $i => $blVal):
                    $blVal = htmlspecialchars($blVal, ENT_QUOTES, 'UTF-8');
                ?>
                <div class="bl-row mb-2" data-index="<?php echo $i; ?>">
                    <input type="text"
                           name="numero_bl[]"
                           placeholder="BL-XXXX"
                           value="<?php echo $blVal; ?>"
                           class="mb-0"
                           required onblur="validateField(this)"
                           style="flex:1; height:45px; padding:0 15px;">
                    <?php if ($i === 0): ?>
                    <button type="button" class="btn btn-primary flex-shrink-0"
                            onclick="addBlRow()" title="Ajouter un bon de livraison"
                            style="height:45px; width:45px; padding:0; border-radius:5px; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-plus"></i>
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-danger flex-shrink-0"
                            onclick="removeBlRow(this)" title="Supprimer"
                            style="height:45px; width:45px; padding:0; border-radius:5px; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="error-msg" id="error_bl">Veuillez renseigner au moins un bon de livraison.</div>
        </div>
    </div>

    <!-- Row 3 : Date Bon Livraison + Type de document -->
    <div class="row align-items-start">
        <div class="col-md-6">
            <label>Date Bon Livraison <span class="req">*</span></label>
            <input type="date" name="date_bl" id="step1_date_bl"
                   required onblur="validateField(this)"
                   onkeydown="return false"
                   value="<?php echo $v('date_bl'); ?>">
            <div class="error-msg">Veuillez renseigner la date du bon de livraison.</div>
        </div>

        <div class="col-md-6">
            <label>Type de document <span class="req">*</span></label>

            <div style="display: flex; align-items: center; gap: 24px; height: 53px; background: #f8f9fa; padding: 0 15px; border-radius: 5px; border: 1px solid #e3e6ec; margin-top: 5px;">

                <label for="type_copie" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-weight: normal;">
                    <input type="radio"
                           id="type_copie"
                           name="type_document"
                           value="copie"
                           style="width: 18px; height: 18px; margin: 0; flex-shrink: 0; accent-color: #0d6efd; cursor: pointer;"
                           <?php echo (isset($_POST['type_document']) && $_POST['type_document'] === 'copie') ? 'checked' : ''; ?>
                           required>
                    Copie
                </label>

                <label for="type_original" style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-weight: normal;">
                    <input type="radio"
                           id="type_original"
                           name="type_document"
                           value="original"
                           style="width: 18px; height: 18px; margin: 0; flex-shrink: 0; accent-color: #0d6efd; cursor: pointer;"
                           <?php echo (isset($_POST['type_document']) && $_POST['type_document'] === 'original') ? 'checked' : ''; ?>
                           required>
                    Original
                </label>

            </div>
            <div class="error-msg" id="error_type_document">Veuillez sélectionner le type de document.</div>
        </div>
    </div>

    <!-- Objet de la facture -->
    <div class="row">
        <div class="col-md-12">
            <label>Objet de la facture</label>
            <input type="text" name="objet_facture"
                   placeholder="Description de la prestation ou du produit"
                   value="<?php echo $v('objet_facture'); ?>">
        </div>
    </div>

    <!-- Row 3 : Montants -->
    <div class="row">
        <div class="col-md-4">
            <label>Montant HT</label>
            <input type="number" step="0.01" min="0" name="montant_ht"
                   placeholder="0.00" value="<?php echo $v('montant_ht'); ?>">
        </div>
        <div class="col-md-4">
            <label>Montant TVA</label>
            <input type="number" step="0.01" min="0" name="montant_tva"
                   placeholder="0.00" value="<?php echo $v('montant_tva'); ?>">
        </div>
        <div class="col-md-4">
            <label>Montant TTC</label>
            <input type="number" step="0.01" min="0" name="montant_ttc"
                   placeholder="0.00" value="<?php echo $v('montant_ttc'); ?>">
        </div>
    </div>

    <!-- Row 4 : Devise + Conditions paiement + Date échéance -->
    <div class="row">
        <div class="col-md-4">
            <label>Devise</label>
            <select name="devise">
                <?php foreach (['MAD','EUR','USD'] as $d): ?>
                <option value="<?php echo $d; ?>"
                    <?php echo (($_POST['devise'] ?? 'MAD') === $d) ? 'selected' : ''; ?>>
                    <?php echo $d; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Conditions de paiement</label>
            <input type="text" name="conditions_paiement"
                   placeholder="ex : 30 jours nets"
                   value="<?php echo $v('conditions_paiement'); ?>">
        </div>
        <div class="col-md-4">
            <label>Date d'échéance</label>
            <input type="date" name="date_echeance"
                    onkeydown="return false"
                   value="<?php echo $v('date_echeance'); ?>">
        </div>
    </div>

    <!-- Row 5 : Nature de l'opération -->
    <div class="row">
        <div class="col-md-6">
            <label>Nature de l'opération</label>
            <select id="nature_operation" name="nature_operation">
                <?php
                $natures = [
                    'matieres_premieres'   => 'Matières premières',
                    'pieces_rechange'      => 'Pièces de rechange',
                    'services_industriels' => 'Services industriels',
                    'transport'            => 'Transport',
                    'location'             => 'Location',
                    'travaux'              => 'Travaux',
                    'autres'               => 'Autres',
                ];
                $selNature = $_POST['nature_operation'] ?? 'autres';
                foreach ($natures as $val => $lbl):
                    echo '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"'
                       . ($selNature === $val ? ' selected' : '') . '>'
                       . htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') . '</option>';
                endforeach; ?>
            </select>
        </div>
        <div class="col-md-6" id="autres_detail_wrap">
            <label>Préciser (si Autres)</label>
            <input type="text" name="nature_autres_detail"
                   placeholder="Précisez..."
                   value="<?php echo $v('nature_autres_detail'); ?>">
        </div>
    </div>

    <!-- Actions -->
    <div class="step-actions">
        <div></div>
        <button type="button" class="btn btn-primary" onclick="nextStep()">
            Suivant <i class="fas fa-arrow-right ms-1"></i>
        </button>
    </div>

</div>

                                <!-- ══ ÉTAPE 2 — Documents + Certification ══ -->
                                <div class="step-content" data-step="2">
                                    <div class="step-indicator">Étape 2 sur 2 — Documents à joindre</div>

                                    <div class="recl-sep">
                                        <i class="fas fa-paperclip"></i>
                                        <span>Documents à joindre</span>
                                        <small>(optionnel — max 10 MB par fichier)</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label>Facture PDF</label>
                                            <input type="file" name="doc_facture_pdf" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label>Attestation fiscale</label>
                                            <input type="file" name="doc_attestation_fisc" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label>RIB bancaire</label>
                                            <input type="file" name="doc_rib" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                    </div>

                                    <!-- ══ Bons de commande — documents dynamiques ══ -->
                                    <div class="recl-sep mt-3">
                                        <i class="fas fa-file-alt"></i>
                                        <span>Documents bons de commande</span>
                                        <small>(un fichier par BC)</small>
                                    </div>
                                    <div id="bc-docs-list">
                                        <!-- rempli dynamiquement par JS -->
                                    </div>

                                    <label>Notes complémentaires <small>(optionnel)</small></label>
                                    <textarea name="notes_fournisseur" rows="4"
                                              placeholder="Toute information supplémentaire..."><?php echo $v('notes_fournisseur'); ?></textarea>

                                    <div class="recl-check">
                                        <input type="checkbox" id="declaration_acceptee"
                                               name="declaration_acceptee" value="1"
                                               <?php echo isset($_POST['declaration_acceptee']) ? 'checked' : ''; ?>>
                                        <label for="declaration_acceptee">
                                            Je certifie l'exactitude des informations fournies et j'autorise leur traitement
                                            par Briqueterie Jbel Annour dans le cadre de ce dépôt de facture.
                                        </label>
                                    </div>

                                    <div class="recl-note">
                                        <i class="fas fa-lock"></i>
                                        <p>
                                            <strong>Note :</strong> La soumission de ce formulaire ne constitue pas un engagement
                                            de paiement. Le dossier est soumis à une vérification de conformité
                                            (BC / Facture) avant tout traitement.
                                        </p>
                                    </div>

                                    <div class="step-actions">
                                        <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                            <i class="fas fa-arrow-left me-1"></i> Précédent
                                        </button>
                                        <button type="submit" class="btn btn-success" id="btnSubmit">
                                            <i class="fas fa-paper-plane me-1"></i> Envoyer la facture
                                        </button>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include('footer.php'); ?>
</main>

<script src="<?php echo _SITE_URL_; ?>/assets/js/jquery.min.js"></script>
<script src="<?php echo _SITE_URL_; ?>/assets/js/popper.min.js"></script>
<script src="<?php echo _SITE_URL_; ?>/assets/js/bootstrap.min.js"></script>
<script src="<?php echo _SITE_URL_; ?>/assets/js/wow.min.js"></script>
<script src="<?php echo _SITE_URL_; ?>/assets/js/counterup.min.js"></script>
<script src="<?php echo _SITE_URL_; ?>/assets/js/jquery.fancybox.min.js"></script>
<script src="<?php echo _SITE_URL_; ?>/assets/js/slick.min.js"></script>
<script src="<?php echo _SITE_URL_; ?>/assets/js/custom-scripts.js"></script>

<script>
let currentStep = 1;
const totalSteps = 2;

function updateStepper() {
    document.getElementById('stepperNav').className =
        'stepper-nav step-' + currentStep + ' d-flex justify-content-between position-relative mb-4 px-3';
    document.querySelectorAll('.step-item').forEach(item => {
        const s = parseInt(item.dataset.step);
        item.classList.remove('active', 'completed', 'future');
        if      (s === currentStep) item.classList.add('active');
        else if (s < currentStep)  item.classList.add('completed');
        else                        item.classList.add('future');
    });
    document.querySelectorAll('.step-content').forEach(c => {
        c.classList.remove('active');
        if (parseInt(c.dataset.step) === currentStep) c.classList.add('active');
    });
    document.querySelector('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function goToStep(step) {
    if (step > currentStep) return;
    currentStep = step;
    updateStepper();
}

// nextStep défini plus bas (avec sync BC docs)

function prevStep() {
    if (currentStep > 1) { currentStep--; updateStepper(); }
}

function validateField(field) {
    const value    = field.value.trim();
    const errorDiv = field.parentElement.querySelector('.error-msg');
    let   isValid  = true;
    if (field.hasAttribute('required') && value === '') isValid = false;
    field.classList.toggle('field-error', !isValid);
    if (errorDiv) errorDiv.classList.toggle('show', !isValid);
    return isValid;
}

function validateCurrentStep() {
    let isValid = true;
    if (currentStep === 1) {
        ['step1_numero', 'step1_date', 'step1_ice', 'step1_date_bl'].forEach(id => {
            const el = document.getElementById(id);
            if (el && !validateField(el)) isValid = false;
        });

        const validateNumberGroup = (selector, errorId) => {
            const inputs = [...document.querySelectorAll(selector)];
            const hasValue = inputs.some(input => input.value.trim() !== '');
            const error = document.getElementById(errorId);
            inputs.forEach(input => input.classList.toggle('field-error', !hasValue));
            if (error) error.classList.toggle('show', !hasValue);
            return hasValue;
        };

        if (!validateNumberGroup('#bc-list input[name="numero_bc[]"]', 'error_bc')) {
            isValid = false;
        }
        if (!validateNumberGroup('#bl-list input[name="numero_bl[]"]', 'error_bl')) {
            isValid = false;
        }

        const typeCopie = document.getElementById('type_copie');
        const typeOriginal = document.getElementById('type_original');
        const errorTypeDoc = document.getElementById('error_type_document');
        
        if (typeCopie && typeOriginal && errorTypeDoc) {
            if (!typeCopie.checked && !typeOriginal.checked) {
                isValid = false;
                errorTypeDoc.classList.add('show');
            } else {
                errorTypeDoc.classList.remove('show');
            }
        }

        const typeFacture = document.getElementById('type_facture_normale');
        const typeAvoir = document.getElementById('type_facture_avoir');
        const errorTypeFacture = document.getElementById('error_type_facture');

        if (typeFacture && typeAvoir && errorTypeFacture) {
            if (!typeFacture.checked && !typeAvoir.checked) {
                isValid = false;
                errorTypeFacture.classList.add('show');
            } else {
                errorTypeFacture.classList.remove('show');
            }
        }
    }
    return isValid;
}

// ── Bons de commande dynamiques ──────────────────────────────────────────
function getBcValues() {
    return [...document.querySelectorAll('#bc-list input[name="numero_bc[]"]')]
        .map(el => el.value.trim());
}

function syncBcDocs() {
    const bcValues = getBcValues();
    const container = document.getElementById('bc-docs-list');
    const existing  = [...container.querySelectorAll('.bc-doc-row')];

    // Ajouter les lignes manquantes
    bcValues.forEach((bc, idx) => {
        let row = container.querySelector(`.bc-doc-row[data-idx="${idx}"]`);
        if (!row) {
            row = document.createElement('div');
            row.className = 'bc-doc-row row mb-2 align-items-center';
            row.dataset.idx = idx;
            row.innerHTML = `
                <div class="col-md-4 d-flex align-items-center" style="gap:8px;">
                    <span class="badge" style="background:var(--theme-color);white-space:nowrap;font-size:12px;padding:5px 10px;">
                        BC <span class="bc-label">${escHtml(bc) || '#' + (idx+1)}</span>
                    </span>
                </div>
                <div class="col-md-8">
                    <input type="file" name="doc_bon_commande[]" accept=".pdf,.jpg,.jpeg,.png"
                           style="margin:0;">
                </div>`;
            container.appendChild(row);
        } else {
            // Mettre à jour le label
            const lbl = row.querySelector('.bc-label');
            if (lbl) lbl.textContent = bc || '#' + (idx+1);
        }
    });

    // Supprimer les lignes en trop
    existing.forEach(row => {
        if (parseInt(row.dataset.idx) >= bcValues.length) row.remove();
    });
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

function addBcRow() {
    const list   = document.getElementById('bc-list');
    const idx    = list.querySelectorAll('.bc-row').length;
    const div    = document.createElement('div');
    div.className = 'bc-row mb-2';
    div.dataset.index = idx;
    div.style.cssText = 'display: flex; flex-direction: row; align-items: center; gap: 8px;';
    div.innerHTML = `
        <input type="text" name="numero_bc[]" placeholder="BC-XXXX"
               class="mb-0" style="flex:1; height:45px; padding:0 15px;"
               required onblur="validateField(this)"
               oninput="syncBcDocs()">
        <button type="button" class="btn btn-danger flex-shrink-0"
                onclick="removeBcRow(this)" title="Supprimer"
                style="height:45px; width:45px; padding:0; border-radius:5px; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-times"></i>
        </button>`;
    list.appendChild(div);
    syncBcDocs();
    div.querySelector('input').focus();
}

function removeBcRow(btn) {
    btn.closest('.bc-row').remove();
    // Renuméroter les index
    document.querySelectorAll('#bc-list .bc-row').forEach((row, i) => {
        row.dataset.index = i;
    });
    syncBcDocs();
}

// Synchroniser les docs BC quand on tape dans un champ BC existant
document.addEventListener('input', function(e) {
    if (e.target.matches('#bc-list input[name="numero_bc[]"]')) syncBcDocs();
});

// ── Bons de livraison dynamiques ─────────────────────────────────────────
function addBlRow() {
    const list = document.getElementById('bl-list');
    const idx = list.querySelectorAll('.bl-row').length;
    const div = document.createElement('div');
    div.className = 'bl-row mb-2';
    div.dataset.index = idx;
    div.innerHTML = `
        <input type="text" name="numero_bl[]" placeholder="BL-XXXX"
               class="mb-0" style="flex:1; height:45px; padding:0 15px;"
               required onblur="validateField(this)">
        <button type="button" class="btn btn-danger flex-shrink-0"
                onclick="removeBlRow(this)" title="Supprimer"
                style="height:45px; width:45px; padding:0; border-radius:5px; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-times"></i>
        </button>`;
    list.appendChild(div);
    div.querySelector('input').focus();
}

function removeBlRow(btn) {
    const list = document.getElementById('bl-list');
    if (list.querySelectorAll('.bl-row').length <= 1) return;
    btn.closest('.bl-row').remove();
    list.querySelectorAll('.bl-row').forEach((row, i) => {
        row.dataset.index = i;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const nature     = document.getElementById('nature_operation');
    const autresWrap = document.getElementById('autres_detail_wrap');
    function toggleAutres() {
        if (nature && autresWrap)
            autresWrap.style.display = nature.value === 'autres' ? 'block' : 'none';
    }
    if (nature) { nature.addEventListener('change', toggleAutres); toggleAutres(); }

    syncBcDocs();
});

// Patch nextStep pour sync les docs BC à chaque changement d'étape
const _nextStepOrig = nextStep;
function nextStep() {
    if (validateCurrentStep() && currentStep < totalSteps) {
        currentStep++;
        updateStepper();
        syncBcDocs();
    }
}


document.getElementById('reclamationForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const cb  = document.getElementById('declaration_acceptee');
    const btn = document.getElementById('btnSubmit');
    if (!cb.checked) {
        alert('Veuillez cocher la case de certification avant de soumettre.');
        return;
    }
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Envoi en cours...';
    this.submit();
});
</script>
</body>
</html>