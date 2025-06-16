<?php

namespace Tests\Mocks;

class GitHubApiResponses
{
    public static function gistsList(): array
    {
        return [
            [
                'id' => 'abc123',
                'description' => 'Test Laravel Helper Functions',
                'created_at' => '2024-01-15T10:30:00Z',
                'files' => [
                    'helpers.php' => [
                        'filename' => 'helpers.php',
                        'language' => 'PHP',
                        'content' => null // Not included in list endpoint
                    ]
                ]
            ],
            [
                'id' => 'def456',
                'description' => 'Vue.js Component Example',
                'created_at' => '2024-02-20T14:45:00Z',
                'files' => [
                    'UserCard.vue' => [
                        'filename' => 'UserCard.vue',
                        'language' => 'Vue',
                        'content' => null
                    ]
                ]
            ],
            [
                'id' => 'ghi789',
                'description' => '',
                'created_at' => '2024-03-10T09:15:00Z',
                'files' => [
                    'untitled.txt' => [
                        'filename' => 'untitled.txt',
                        'language' => null,
                        'content' => null
                    ]
                ]
            ]
        ];
    }

    public static function singleGist(string $gistId = 'abc123'): array
    {
        $gists = [
            'abc123' => [
                'id' => 'abc123',
                'description' => 'Test Laravel Helper Functions',
                'created_at' => '2024-01-15T10:30:00Z',
                'files' => [
                    'helpers.php' => [
                        'filename' => 'helpers.php',
                        'language' => 'PHP',
                        'content' => '<?php

function format_money($amount)
{
    return "$" . number_format($amount, 2);
}

function truncate_string($string, $limit = 100)
{
    return strlen($string) > $limit
        ? substr($string, 0, $limit) . "..."
        : $string;
}

function is_valid_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}'
                    ]
                ]
            ],
            'def456' => [
                'id' => 'def456',
                'description' => 'Vue.js Component Example',
                'created_at' => '2024-02-20T14:45:00Z',
                'files' => [
                    'UserCard.vue' => [
                        'filename' => 'UserCard.vue',
                        'language' => 'Vue',
                        'content' => '<template>
  <div class="user-card">
    <img :src="user.avatar" :alt="user.name" />
    <h3>{{ user.name }}</h3>
    <p>{{ user.email }}</p>
  </div>
</template>

<script>
export default {
  props: {
    user: {
      type: Object,
      required: true
    }
  }
}
</script>

<style scoped>
.user-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 16px;
  text-align: center;
}
</style>'
                    ]
                ]
            ],
            'ghi789' => [
                'id' => 'ghi789',
                'description' => '',
                'created_at' => '2024-03-10T09:15:00Z',
                'files' => [
                    'untitled.txt' => [
                        'filename' => 'untitled.txt',
                        'language' => null,
                        'content' => 'Just some random notes for testing purposes.'
                    ]
                ]
            ]
        ];

        return $gists[$gistId] ?? $gists['abc123'];
    }

    public static function emptyGistsList(): array
    {
        return [];
    }

    public static function apiErrorResponse(): array
    {
        return [
            'message' => 'Not Found',
            'documentation_url' => 'https://docs.github.com/rest'
        ];
    }

    public static function rateLimitResponse(): array
    {
        return [
            'message' => 'API rate limit exceeded',
            'documentation_url' => 'https://docs.github.com/rest'
        ];
    }
}