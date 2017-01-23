<html>
    <head>
         <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    </head>
    <body style="background-color:lightpink;">
        <div>
            {{ Html::image('img/failure.png', 'Failure', ['class' => 'img-responsive center-block']) }}
            <h2><p class="text-center">Authentication Failed. <br />Please contact support or try again.</p></h2>
            <p class="text-center">Reference Number: {{ $referenceId }}</p>
        </div>
    </body>
</html>