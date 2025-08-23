<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>LGU Quick Appoint</title>
<!-- FontAwesome CDN for icons -->
<link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
/>
<style>
  /* Reset some default styles */
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body,
  html {
    height: 100%;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #f0f0f0;
  }

  /* Background image styling */
  body {
    background: url('images/LGU_Unisan.jpg')  
      no-repeat center center fixed;
    background-size: cover;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  /* Overlay for better readability */
  body::before {
    content: "";
    position: fixed;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.61);
    z-index: -1;
  }

  /* Header */
  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 3rem;
    background-image: linear-gradient(to right, #0D92F4, #27548A);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);

  }

  header .logo {
    font-size: 1.8rem;
    font-weight: 700;
    color:rgb(21, 79, 255);
    letter-spacing: 2px;
    user-select: none;
  }

  nav ul {
    list-style: none;
    display: flex;
    gap: 2.5rem;
  }

  nav ul li a {
    color:white;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: color 0.3s ease;
    cursor: pointer;
    padding-bottom: 3px;
  }

  nav ul li a:hover,
  nav ul li a:focus {
    color:#2299ee;
    border-bottom: 2px solid #2299ee;
  }

  /* Main content */
  main {
    flex-grow: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 2rem 1rem;
  }

  main h1 {
    font-size: 3rem;
    max-width: 700px;
    line-height: 1.3;
    text-shadow: 0 0 10px rgba(18, 147, 151, 0.17);
    color:#27548A;
  }

  /* Footer */
  footer {
    background-image: linear-gradient(to right, #0D92F4, #27548A);
    padding: 0.6rem 3rem;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    font-size: 0.9rem;
    color: #c3dbe8;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);

  }

  footer .about,
  footer .contacts {
    max-width: 45%;
    margin-bottom: 1rem;
  }

  footer .about h3,
  footer .contacts h3 {
    margin-bottom: 0.75rem;
    color:rgb(255, 255, 255);
  }

  footer .contacts ul {
    list-style: none;
    padding-left: 0;
  }

  footer .contacts ul li {
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    cursor: default;
  }

  footer .contacts ul li i {
    color:rgb(255, 255, 255);
    font-size: 1.3rem;
    width: 25px;
    text-align: center;
  }

  footer .contacts ul li a {
    color:rgb(255, 255, 255);
    text-decoration: none;
    transition: color 0.3s ease;
  }

  footer .contacts ul li a:hover,
  footer .contacts ul li a:focus {
    color: #2299ee;
    
  }

  /* Responsive adjustments */
  @media (max-width: 720px) {
    header {
      flex-direction: column;
      gap: 1rem;
      padding: 1rem 2rem;
    }

    nav ul {
      gap: 1.5rem;
      flex-wrap: wrap;
      justify-content: center;
    }

    main h1 {
      font-size: 2.2rem;
      max-width: 90%;
    }

    footer {
      flex-direction: column;
      gap: 1.5rem;
    }

    footer .about,
    footer .contacts {
      max-width: 100%;
    }

  }
</style>
</head>
<body>
<header>
  <div class="logo" tabindex="0" style="color: #023e8a;">LGU Quick Appoint</div>
  <nav>
    <ul>
      <li><a href="register.php" tabindex="0">Register</a></li>
      <li><a href="login.php" tabindex="0">Login</a></li>
    </ul>
  </nav>
</header>
<main style="
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  text-align: center;
  padding: 2rem;
  background: linear-gradient(135deg,rgba(224, 247, 250, 0.14), #ffffff);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
">
 <img src="images/logo.png" alt="LGU Logo" class="logo" style="border: 1px; width: 100px; height: 100px; border-radius: 50%; margin-top: -16px; margin-bottom: -15px;" tabindex="0">
  <h1 style="
    font-size: 2.5rem;
    color: #0077b6;
    margin-bottom: 1rem;
    text-shadow: 1px 1px 2px rgba(0, 119, 182, 0.1);
  ">
    Welcome to <span style="color: #023e8a;">LGU Quick Appoint</span>
  </h1>
  <p style="
    font-size: 1.2rem;
    color: #333;
    max-width: 600px;
  ">
    Book appointments with your local government units quickly and easily.
    Empowering residents with efficient and transparent public service.
  </p>
  <a href="register.php" style="
    margin-top: 2rem;
    display: inline-block;
    background-color: #0077b6;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s;
  " onmouseover="this.style.backgroundColor='#023e8a'" onmouseout="this.style.backgroundColor='#0077b6'">
    Get Started
  </a>
</main>

<footer>
  <section class="about">
    <h3 style="color: white;">About the system</h3>
    <p>
      LGU Quick Appoint is a revolutionary platform designed to streamline appointment scheduling for Local Government Units,
      providing residents with easy access to services and reducing wait times through efficient resource management.
    </p>
  </section>
  <section class="contacts">
    <h3>Contacts</h3>
    <ul>
      <li>
        <i class="fab fa-facebook-f" aria-hidden="true"></i>
        <a href="https://facebook.com" target="_blank" rel="noopener" tabindex="0">Facebook</a>
      </li>
      <li>
        <i class="fab fa-instagram" aria-hidden="true"></i>
        <a href="https://instagram.com" target="_blank" rel="noopener" tabindex="0">Instagram</a>
      </li>
      <li>
        <i class="fas fa-envelope" aria-hidden="true"></i>
        <a href="mailto:contact@lguquickappoint.com" tabindex="0">contact@lguquickappoint.com</a>
      </li>
    </ul>
  </section>
</footer>
</body>
</html>
