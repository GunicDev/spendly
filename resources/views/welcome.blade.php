<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Spendly</title>

        <style>
            :root {
                color-scheme: light dark;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: #f6f7f9;
                color: #171717;
            }

            * {
                box-sizing: border-box;
            }

            body {
                min-height: 100vh;
                margin: 0;
                display: grid;
                place-items: center;
                padding: 24px;
                background:
                    linear-gradient(135deg, rgba(245, 158, 11, 0.12), transparent 36%),
                    linear-gradient(315deg, rgba(20, 184, 166, 0.1), transparent 32%),
                    #f6f7f9;
            }

            main {
                width: min(100%, 560px);
                padding: 40px;
                border: 1px solid rgba(23, 23, 23, 0.08);
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.88);
                box-shadow: 0 24px 80px rgba(15, 23, 42, 0.08);
            }

            .brand {
                margin: 0 0 16px;
                font-size: 14px;
                font-weight: 700;
                letter-spacing: 0;
                color: #b45309;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                font-size: 42px;
                line-height: 1.05;
                letter-spacing: 0;
            }

            p {
                margin: 18px 0 28px;
                color: #525252;
                font-size: 16px;
                line-height: 1.7;
            }

            a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 0 18px;
                border-radius: 8px;
                background: #171717;
                color: #ffffff;
                font-weight: 700;
                text-decoration: none;
            }

            a:focus-visible {
                outline: 3px solid rgba(245, 158, 11, 0.45);
                outline-offset: 3px;
            }

            @media (max-width: 520px) {
                main {
                    padding: 28px;
                }

                h1 {
                    font-size: 34px;
                }
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    background: #101010;
                    color: #f5f5f5;
                }

                body {
                    background:
                        linear-gradient(135deg, rgba(245, 158, 11, 0.14), transparent 34%),
                        linear-gradient(315deg, rgba(20, 184, 166, 0.12), transparent 34%),
                        #101010;
                }

                main {
                    border-color: rgba(255, 255, 255, 0.09);
                    background: rgba(23, 23, 23, 0.88);
                    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.28);
                }

                .brand {
                    color: #f59e0b;
                }

                p {
                    color: #c8c8c8;
                }

                a {
                    background: #f5f5f5;
                    color: #171717;
                }
            }
        </style>
    </head>
    <body>
        <main>
            <p class="brand">Spendly</p>
            <h1>Manage your workspace.</h1>
            <p>Access your dashboard to review users, account details, and spending tools.</p>
            <a href="{{ url('/spendly') }}">Open dashboard</a>
        </main>
    </body>
</html>
