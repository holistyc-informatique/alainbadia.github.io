<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.html");
    exit;
}

// 1. SÉCURITÉ POT DE MIEL (Honeypot) : Si rempli, c'est un spameur automatique.
if (!empty($_POST['verification_address'])) {
    // On simule un succès pour que le robot ne cherche pas à forcer une autre faille
    header("Location: index.html?status=success#contact");
    exit;
}

// 2. RÉCUPÉRATION ET ASSAINISSEMENT STRICT DES DONNÉES
$nom = trim(htmlspecialchars($_POST['nom'] ?? ''));
$email = trim(filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL));
$sujet = trim(htmlspecialchars($_POST['sujet'] ?? ''));
$message = trim(htmlspecialchars($_POST['message'] ?? ''));

// Vérification des champs requis obligatoires
if (empty($nom) || !$email || empty($message)) {
    header("Location: index.html?status=error#contact");
    exit;
}

// Nettoyage anti-injection de headers (suppression des retours à la ligne malicieux)
$nom_clean = str_replace(array("\r", "\n"), '', $nom);
$email_clean = str_replace(array("\r", "\n"), '', $email);
$sujet_clean = !empty($sujet) ? str_replace(array("\r", "\n"), '', $sujet) : "Nouveau contact depuis le site";

// 3. ENREGISTREMENT SÉCURISÉ DANS LA BASE SQLITE (Dossier blog/)
$db_path = 'blog/database/blog.sqlite';
try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA encoding = 'UTF-8'");

    // Création à la volée de la table messages si absente
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT,
        email TEXT,
        sujet TEXT,
        message TEXT,
        created_at DATETIME
    )");

    $stmt = $pdo->prepare("INSERT INTO messages (nom, email, sujet, message, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
    $stmt->execute([$nom_clean, $email_clean, $sujet_clean, $message]);
} catch (Exception $e) {
    // Si la base échoue temporairement, on laisse le script continuer pour tenter d'envoyer l'email
}

// 4. ROUTAGE ET ENVOI SÉCURISÉ DE L'EMAIL DE CONTACT
$to = "alainbadia.psychanalyste@gmail.com"; // Adresse officielle d'Alain mise à jour

$email_subject = "[Cabinet Web] " . $sujet_clean;

$email_body = "Vous avez reçu un nouveau message depuis le formulaire de votre site internet.\n\n";
$email_body .= "--------------------------------------------------\n";
$email_body .= "Nom / Expéditeur : " . $nom_clean . "\n";
$email_body .= "Adresse Email   : " . $email_clean . "\n";
$email_body .= "Sujet           : " . $sujet_clean . "\n";
$email_body .= "--------------------------------------------------\n\n";
$email_body .= "Message :\n" . $message . "\n\n";
$email_body .= "--------------------------------------------------\n";
$email_body .= "Note : Ce message est également sauvegardé en sécurité dans votre espace d'administration.";

$headers = array(
    'From' => 'site-internet@alainbadia.fr', // Mail du domaine pour maximiser la délivrabilité
    'Reply-To' => $email_clean,
    'X-Mailer' => 'PHP/' . phpversion(),
    'Content-Type' => 'text/plain; charset=UTF-8'
);

// Envoi et redirection finale vers l'ancre de contact
mail($to, $email_subject, $email_body, $headers);
header("Location: index.html?status=success#contact");
exit;