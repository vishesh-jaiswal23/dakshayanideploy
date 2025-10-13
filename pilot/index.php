<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dakshayani AI Co-Pilot Login</title>
    <link rel="stylesheet" href="../style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="auth-page" data-active-nav="login">
    <header class="site-header"></header>

    <main>
        <section class="auth-wrapper">
            <div class="auth-card">
                <h1 class="text-3xl font-extrabold">AI Co-Pilot Access</h1>
                <p class="mt-2">Log in to orchestrate workflows, monitor performance, and automate service delivery.</p>

                <form action="index.php" method="POST" class="mt-6">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                    </div>
                    <div class="auth-actions">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            Log In
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <footer class="site-footer"></footer>

    <script src="../script.js" defer></script>
</body>
</html>
