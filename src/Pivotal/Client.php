<?php

namespace Dilling\PostItPrinter\Pivotal;

use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;

class Client
{
    private $client;

    /**
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => 'https://www.pivotaltracker.com/services/v5/',
            'headers' => [
                'X-TrackerToken' => $token,
//                'X-Tracker-Pagination-Limit' => 10,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getProjects() : Collection
    {
        $response = $this->client->get('projects');

        return new Collection($this->decode($response));
    }

    public function getProject(int $id) : array
    {
        $response = $this->client->get("projects/{$id}");

        return $this->decode($response);
    }

    /**
     * @param integer $projectId
     *
     * @return Collection
     */
    public function getStories($projectId) : Collection
    {
        $response = $this->client->get("projects/{$projectId}/stories?filter=state:unstarted");

        return new Collection($this->decode($response));
    }

    public function getStory(int $projectId, int $storyId) : array
    {
        $response = $this->client->get("projects/{$projectId}/stories/{$storyId}");

        return $this->decode($response);
    }

    /**
     * @param int $projectId
     * @param int|null $afterId
     * @param int|null $beforeId
     *
     * @return Collection
     */
    public function getStoriesBetween(int $projectId, ?int $afterId, ?int $beforeId) : Collection
    {
        $query = \array_filter([
            'after_story_id' => $afterId,
            'before_story_id' => $beforeId,
        ]);
        $response = $this->client->get("projects/{$projectId}/stories?" . \http_build_query($query));

        return new Collection($this->decode($response));
    }

    private function decode(ResponseInterface $response) : array
    {
        return \json_decode($response->getBody()->getContents(), true);
    }
}
