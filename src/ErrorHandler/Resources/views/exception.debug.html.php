<!-- <?= $_message = sprintf('%s (%d %s)', $exceptionMessage, $statusCode, $statusText); ?> -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="<?= $this->charset; ?>" />
        <meta name="robots" content="noindex,nofollow" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title><?= $_message; ?></title>
        <link rel="icon" type="image/png" href="<?= $this->include('assets/images/favicon.png.base64'); ?>">
        <style><?= $this->include('assets/css/exception.css'); ?></style>
        <style><?= $this->include('assets/css/exception_full.css'); ?></style>
    </head>
    <body>
        <script>
            document.body.classList.add(
                localStorage.getItem('symfony/profiler/theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'theme-dark' : 'theme-light')
            );
        </script>

        <?php if (class_exists(\TeraBlaze\Core\Kernel\Kernel::class)) { ?>
            <header>
                <div class="container">
                    <h1 class="logo">TeraBlaze Exception</h1>

                    <div class="help-link">
                        <a href="https://github.com/terablaze/terablaze<?= \TeraBlaze\Core\Kernel\Kernel::TERABLAZE_VERSION; ?>/index.html">
                            <span class="icon"><?= $this->include('assets/images/icon-book.svg'); ?></span>
                            <span class="hidden-xs-down">TeraBlaze</span> Docs
                        </a>
                    </div>

                    <div class="help-link">
                        <a href="#">
                            <span class="icon"><?= $this->include('assets/images/icon-support.svg'); ?></span>
                            <span class="hidden-xs-down">TeraBlaze</span> Support
                        </a>
                    </div>
                </div>
            </header>
        <?php } ?>

        <?= $this->include('views/exception.html.php', $context); ?>

        <script>
            <?= $this->include('assets/js/exception.js'); ?>
        </script>
    </body>
</html>
<!-- <?= $_message; ?> -->
