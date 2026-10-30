<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Authorization\Console\MakePolicyCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * make:policy writing into a temp app/ directory; generated code is linted.
 *
 * @package Tests\Console
 */
#[CoversClass(MakePolicyCommand::class)]
final class MakePolicyCommandTest extends TestCase
{
    private string $appPath = '';

    /** @var resource */
    private mixed $errors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appPath = sys_get_temp_dir() . '/ez-make-policy-' . uniqid();
        $errors = fopen('php://memory', 'w+');
        self::assertIsResource($errors);
        $this->errors = $errors;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->appPath . '/Policies/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->appPath . '/Policies')) {
            rmdir($this->appPath . '/Policies');
        }

        if (is_dir($this->appPath)) {
            rmdir($this->appPath);
        }

        parent::tearDown();
    }

    /**
     * @return array{int, string, string}
     */
    private function runMake(string ...$args): array
    {
        ob_start();
        $code = (new MakePolicyCommand($this->appPath, $this->errors))->handle(array_values($args));
        $output = (string) ob_get_clean();
        rewind($this->errors);

        return [$code, $output, (string) stream_get_contents($this->errors)];
    }

    public function test_creates_a_policy_typed_to_the_model(): void
    {
        [$code, $output] = $this->runMake('PostPolicy', '--model=Post');
        $file = $this->appPath . '/Policies/PostPolicy.php';
        $source = (string) file_get_contents($file);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: app/Policies/PostPolicy.php', $output);
        $this->assertStringContainsString('$gate->policy(\\App\\Entities\\Post::class, \\App\\Policies\\PostPolicy::class);', $output);
        $this->assertStringContainsString('use App\\Entities\\Post;', $source);
        $this->assertStringContainsString('public function update(UserInterface $user, Post $subject): bool', $source);
        $this->assertStringContainsString('public function create(UserInterface $user, string $class): bool', $source);
        // Generated code follows the guidelines: a docblock on every public method.
        $this->assertSame(0, preg_match('/[^\/]\n\s*public function/', $source), 'public method without a docblock');
        $this->assertValidPhp($file);
    }

    public function test_without_model_the_subject_is_mixed(): void
    {
        [$code] = $this->runMake('ReportPolicy');
        $source = (string) file_get_contents($this->appPath . '/Policies/ReportPolicy.php');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('mixed $subject', $source);
        $this->assertStringNotContainsString('App\\Entities', $source);
        $this->assertValidPhp($this->appPath . '/Policies/ReportPolicy.php');
    }

    public function test_rejects_invalid_names_and_existing_files(): void
    {
        $this->assertSame(1, $this->runMake()[0]);
        $this->assertSame(1, $this->runMake('../Evil')[0]);
        $this->assertSame(1, $this->runMake('PostPolicy', '--model=Bad-Name')[0]);

        $this->runMake('PostPolicy');
        [$code, , $errors] = $this->runMake('PostPolicy');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Policy already exists: PostPolicy.php', $errors);
        $this->assertSame('make:policy', (new MakePolicyCommand())->getName());
    }

    private function assertValidPhp(string $file): void
    {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
    }
}
