<?php

declare(strict_types=1);

namespace EzPhp\Authorization\Console;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;

/**
 * Class MakePolicyCommand
 *
 * `make:policy <Name> [--model=Post]` — writes `app/Policies/<Name>.php`, a
 * plain policy class with view/create/update/delete methods. With `--model`,
 * the methods take `App\Entities\<Model>` and the output names the
 * `$gate->policy()` line to add; the policy is never registered automatically.
 *
 * @package EzPhp\Authorization\Console
 */
final readonly class MakePolicyCommand implements CommandInterface
{
    private const string NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9]*$/';

    /**
     * @param string|null   $appPath     The application's `app/` directory; null = `<cwd>/app`.
     * @param resource|null $errorStream Where errors go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private ?string $appPath = null,
        private mixed $errorStream = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:policy';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new authorization policy class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:policy <ClassName> [--model=EntityClass]';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $input = new Input($args);
        $name = $input->argument(0);
        $model = $input->option('model');

        if ($name === null || preg_match(self::NAME_PATTERN, $name) !== 1 || ($model !== '' && preg_match(self::NAME_PATTERN, $model) !== 1)) {
            fwrite($this->errorStream ?? STDERR, "Usage: ez make:policy <ClassName> [--model=EntityClass]\n");

            return 1;
        }

        $dir = ($this->appPath ?? (getcwd() ?: '.') . DIRECTORY_SEPARATOR . 'app') . DIRECTORY_SEPARATOR . 'Policies';
        $fullPath = $dir . DIRECTORY_SEPARATOR . "$name.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite($this->errorStream ?? STDERR, "Policy already exists: $name.php\n");

            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name, $model)) === false) {
            fwrite($this->errorStream ?? STDERR, "Failed to create policy: $name.php\n");

            return 1;
        }

        echo "Created: app/Policies/$name.php\n";

        if ($model !== '') {
            echo "Register it: \$gate->policy(\\App\\Entities\\$model::class, \\App\\Policies\\$name::class);\n";
        }

        return 0;
    }

    /**
     * @param string $name
     * @param string $model Entity short name, or '' for an untyped subject.
     *
     * @return string
     */
    private function stub(string $name, string $model): string
    {
        $use = $model !== '' ? "use App\\Entities\\$model;\n" : '';
        $subject = $model !== '' ? "$model \$subject" : 'mixed $subject';

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Policies;

            {$use}use EzPhp\\Auth\\UserInterface;

            /**
             * Called by the Gate as <ability>(\$user, \$subject); a missing method denies.
             */
            final class $name
            {
                /**
                 * Whether the user may view the subject.
                 */
                public function view(UserInterface \$user, $subject): bool
                {
                    return false;
                }

                /**
                 * Class-level check: the subject is the class-string.
                 */
                public function create(UserInterface \$user, string \$class): bool
                {
                    return false;
                }

                /**
                 * Whether the user may update the subject.
                 */
                public function update(UserInterface \$user, $subject): bool
                {
                    return false;
                }

                /**
                 * Whether the user may delete the subject.
                 */
                public function delete(UserInterface \$user, $subject): bool
                {
                    return false;
                }
            }

            PHP;
    }
}
