<?php

namespace App\Commands;

use Aws\Iam\IamClient;
use Aws\S3\S3Client;
use LaravelZero\Framework\Commands\Command;

class CreateUserAndBucketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-user-and-bucket-command
                            {--profile=default : AWS credentials profile from ~/.aws/credentials}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a user and a bucket on AWS';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $profile = $this->option('profile');

        // Read AWS credentials from ~/.aws/credentials
        $credentials = $this->getAwsCredentials($profile);

        if ($credentials === null) {
            return Command::FAILURE;
        }

        // Read AWS region from ~/.aws/config (defaults to ca-central-1)
        $awsRegion = $this->getAwsRegion($profile);

        $this->info("Using AWS profile: [{$profile}], region: {$awsRegion}");

        // Input for new user and bucket
        $username = $this->ask('Enter the username for the new IAM user (no underscores, dashes are allowed, lowercase only)');
        $bucketName = $this->ask('Enter the name for the S3 bucket (no underscores, dashes are allowed, lowercase only)');

        // AWS client configuration with credentials
        $awsConfig = [
            'version' => 'latest',
            'region' => $awsRegion,
            'credentials' => [
                'key' => $credentials['aws_access_key_id'],
                'secret' => $credentials['aws_secret_access_key'],
            ],
        ];

        // Initialize AWS clients
        $iamClient = new IamClient($awsConfig);
        $s3Client = new S3Client($awsConfig);

        try {
            // Create IAM user
            $this->info('Creating IAM user...');
            $userResult = $iamClient->createUser([
                'UserName' => $username,
            ]);
            $userArn = $userResult['User']['Arn'];
            $this->info("User created: {$userArn}");

            // Create S3 bucket
            $this->info('Creating S3 bucket...');
            $bucketResult = $s3Client->createBucket([
                'Bucket' => $bucketName,
                'CreateBucketConfiguration' => [
                    'LocationConstraint' => 'ca-central-1',
                ],
            ]);
            $bucketLocation = $bucketResult['Location'];
            $this->info("Bucket created: {$bucketLocation}");

            // Create access key for the user
            $this->info('Creating access key...');
            $accessKeyResult = $iamClient->createAccessKey([
                'UserName' => $username,
            ]);
            $accessKeyId = $accessKeyResult['AccessKey']['AccessKeyId'];
            $secretAccessKey = $accessKeyResult['AccessKey']['SecretAccessKey'];
            $this->info('Access key created.');

            // Create policy to allow S3 access only to the specified bucket
            $userPolicy = [
                'Version' => '2012-10-17',
                'Statement' => [
                    [
                        'Effect' => 'Allow',
                        'Action' => [
                            's3:GetObject',
                            's3:PutObject',
                            's3:ListBucket',
                            's3:DeleteObject',
                        ],
                        'Resource' => [
                            "arn:aws:s3:::{$bucketName}/*",
                            "arn:aws:s3:::{$bucketName}",
                        ],
                    ],
                ],
            ];

            // Attach the user policy
            $this->info('Attaching policy to user...');
            $iamClient->putUserPolicy([
                'UserName' => $username,
                'PolicyName' => 'S3BucketPolicy',
                'PolicyDocument' => json_encode($userPolicy),
            ]);
            $this->info('Policy attached.');

            // Display access key information
            $this->newLine(2);
            $this->info("Access Key ID: {$accessKeyId}");
            $this->info("Secret Access Key: {$secretAccessKey}");
            $this->info("User Arn: {$userArn}");
            $this->info("Bucket location: {$bucketLocation}");
            $this->newLine(2);

            // Display .env formatted output
            $this->line('Copy and paste into .env file');
            $this->newLine();
            $this->line("AWS_ACCESS_KEY_ID={$accessKeyId}");
            $this->line("AWS_SECRET_ACCESS_KEY={$secretAccessKey}");
            $this->line("AWS_DEFAULT_REGION={$awsRegion}");
            $this->line("AWS_BUCKET={$bucketName}");
            $this->line('AWS_USE_PATH_STYLE_ENDPOINT=false');
            $this->newLine(2);

            $this->info('User, S3 bucket, access key, and policies setup complete!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Read AWS credentials from ~/.aws/credentials file.
     *
     * @param string $profile
     * @return array|null
     */
    protected function getAwsCredentials(string $profile): ?array
    {
        $credentialsPath = $_SERVER['HOME'] . '/.aws/credentials';

        if (!file_exists($credentialsPath)) {
            $this->error("AWS credentials file not found at: {$credentialsPath}");
            $this->line('Please run "aws configure" to set up your AWS credentials.');
            return null;
        }

        $credentials = parse_ini_file($credentialsPath, true);

        if ($credentials === false) {
            $this->error('Failed to parse AWS credentials file.');
            return null;
        }

        if (!isset($credentials[$profile])) {
            $this->error("Profile [{$profile}] not found in AWS credentials file.");
            $this->line('Available profiles: ' . implode(', ', array_keys($credentials)));
            return null;
        }

        $profileData = $credentials[$profile];

        if (!isset($profileData['aws_access_key_id']) || !isset($profileData['aws_secret_access_key'])) {
            $this->error("Profile [{$profile}] is missing required credentials.");
            return null;
        }

        return $profileData;
    }

    /**
     * Read AWS region from ~/.aws/config file.
     *
     * @param string $profile
     * @return string
     */
    protected function getAwsRegion(string $profile): string
    {
        $defaultRegion = 'ca-central-1';
        $configPath = $_SERVER['HOME'] . '/.aws/config';

        if (!file_exists($configPath)) {
            return $defaultRegion;
        }

        $config = parse_ini_file($configPath, true);

        if ($config === false) {
            return $defaultRegion;
        }

        // In config file, non-default profiles are prefixed with "profile "
        $profileKey = $profile === 'default' ? 'default' : "profile {$profile}";

        if (isset($config[$profileKey]['region'])) {
            return $config[$profileKey]['region'];
        }

        // Fallback to default profile's region if specific profile doesn't have one
        if ($profile !== 'default' && isset($config['default']['region'])) {
            return $config['default']['region'];
        }

        return $defaultRegion;
    }
}
