<?php
// =============================================================================
// FILE: about.php
// PURPOSE: Static informational page describing the WildRose Portal project and
//          introducing the FireNode development team. No PHP logic or database
//          interaction.
//
// LINKED CSS:  assets/css/about.css
// NAVIGATION:  Accessible from admin.php via the "About WildRose Portal" button
// =============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- See head.txt for more information -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About</title>
    <link rel="icon" type="image/png" href="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png">
    <link rel="stylesheet" href="http://customer.altismsp.com/assets/css/about.css">
</head>
<body>

    <!-- ── Nav Bar ──────────────────────────────────────────────────────────── -->
    <nav class="nav-bar">
        <img src="http://customer.altismsp.com/assets/images/altisMSPLogoSmall.png" width="100" height="39" alt="Altis MSP logo blue & green">
    </nav>

    <!-- ── Main Content ─────────────────────────────────────────────────────── -->
    <div class="page-wrapper">

        <!-- WildRose Portal logo displayed at the top of the page -->
        <div class="header-container">
            <img src="http://customer.altismsp.com/assets/images/WildRosePortalLogo.png"
                alt="WildRose Portal Logo">
        </div>

        <!-- ── What is WildRose Portal? ─────────────────────────────────────── -->
        <div class="section">
            <h3 class="section-heading">What is WildRose Portal?</h3>
            <p class="description">The WildRose Portal is a web app software solution for digitizing a physical paper-form device check-in system. It's complete with required fields so nothing gets missed, the ability to lookup returning customers and autofill their information, the ability to take pictures of customer devices, capture electronic signatures, generate reports, and a database to store relevant information.</p>
        </div>

        <!-- ── Who is FireNode? ──────────────────────────────────────────────── -->
        <div class="section">
            <h3 class="section-heading">Who is FireNode?</h3>
            <p class="description">FireNode is a team of six CIT students from the Systems Analysis & Design course, each with their respective lead role. Each member of the team contributed to every area of the project but took charge in different departments. Bryan and Cole are the Primary Contacts for AltisMSP.</p>

            <!-- FireNode team logo and institution credit -->
            <div class="firenode-logo-block">
                <img src="http://customer.altismsp.com/assets/images/FireNodeLogo.png"
                    alt="FireNode Logo blue/purple fire with six nodes">
                <p class="description"><b>Computer Information Technology, Lethbridge Polytechnic, 2026</b></p>
            </div>

            <!-- ── Team Member Cards ─────────────────────────────────────────── -->
            <!-- Each card contains a photo, name, and bio for one team member  -->
            <div class="team-grid">

                <!-- Bryan Tobias — Project Lead & Primary Contact -->
                <div class="member-card">
                    <img class="member-photo" src="http://customer.altismsp.com/assets/images/Bryan.jpg" alt="Photo of Bryan Tobias">
                    <div class="member-info">
                        <p class="member-name">Bryan Tobias</p>
                        <p class="member-bio">Project Lead & Primary Contact. Keeping the team organized and ensuring there is a sense of direction, I am a second year CIT Student and currently learning as I go and trying to put into practice everything we've learned. Thus, as I learn from my instructors and teammates I aim to improve my skills. My goal is to ensure client business requirements and needs are met. On my off time, I like to hang out with my dog, my family and friends, play video games and read a book or two!</p>
                    </div>
                </div>

                <!-- Christopher Marynowski — Information Lead -->
                <div class="member-card">
                    <img class="member-photo" src="http://customer.altismsp.com/assets/images/Chris.jpg" alt="Photo of Christopher Marynowski">
                    <div class="member-info">
                        <p class="member-name">Christopher Marynowski</p>
                        <p class="member-bio">Information Lead. Currently completing a Diploma in Computer Information Technology at Lethbridge Polytechnic. Chris hopes to continue in the IT industry. He specializes in hardware and networking.</p>
                    </div>
                </div>

                <!-- Branden De Grasse — Database Lead -->
                <div class="member-card">
                    <img class="member-photo" src="http://customer.altismsp.com/assets/images/Branden.jpg" alt="Photo of Branden De Grasse">
                    <div class="member-info">
                        <p class="member-name">Branden De Grasse</p>
                        <p class="member-bio">Database Lead. Emerging IT professional currently completing a Diploma in Computer Information Technology at Lethbridge Polytechnic, with a Bachelor of Fine Arts in New Media providing a solid foundation in UX, design, and problem-solving. Skilled in Python, SQL, Git. Collaborative, analytical, and committed to continuous learning in software development and cybersecurity.</p>
                    </div>
                </div>

                <!-- Carson Slomp — Infrastructure Lead -->
                <div class="member-card">
                    <img class="member-photo" src="http://customer.altismsp.com/assets/images/Carson.jpg" alt="Photo of Carson Slomp">
                    <div class="member-info">
                        <p class="member-name">Carson Slomp</p>
                        <p class="member-bio">Infrastructure Lead. Carson is currently completing a Diploma in Computer Information Technology at Lethbridge Polytechnic. As Infrastructure Lead for FireNode, he oversaw the server environment and database architecture that powered WildRose Portal. He has a particular interest in IT infrastructure and endpoint support, and is looking forward to putting his skills to work in the field after graduation.</p>
                    </div>
                </div>

                <!-- Cole Russell — Programming Lead & Primary Contact -->
                <div class="member-card">
                    <img class="member-photo" src="http://customer.altismsp.com/assets/images/Cole.jpg" alt="Photo of Cole Russell">
                    <div class="member-info">
                        <p class="member-name">Cole Russell</p>
                        <p class="member-bio">Programming Lead & Primary Contact. Cole is currently pursuing a Bachelor of Computer Science with a minor in Japanese. His long‑term goal is to join the JET Program, where he hopes to be found teaching English and coding off the coast of Japan! He enjoys full-stack development and specializes in C# and Python.</p>
                    </div>
                </div>

                <!-- Joey Peters — UI/UX Lead -->
                <div class="member-card">
                    <img class="member-photo" src="http://customer.altismsp.com/assets/images/Joey.jpg" alt="Photo of Joey Peters">
                    <div class="member-info">
                        <p class="member-name">Joey Peters</p>
                        <p class="member-bio">UI/UX Lead. Joey is completing the Computer Information Technologies diploma at the Lethbridge Polytechnic. He plans to experiment in a few different areas afterwards such as game design, software development, and visual design. He enjoys spending his spare time picking apart video games and honing his logic and problem solving skills.</p>
                    </div>
                </div>

            </div><!-- /team-grid -->
        </div><!-- /section -->

        <!-- ── Back Button ───────────────────────────────────────────────────── -->
        <div class="back-btn-wrapper">
            <a class="back-btn" href="http://customer.altismsp.com/public/admin.php">Back</a>
        </div>

    </div><!-- /page-wrapper -->

    <!-- ── Footer ───────────────────────────────────────────────────────────── -->
    <footer>
        <p class="copyright">© 2026 FireNode & AltisMSP</p>
    </footer>

</body>
</html>
