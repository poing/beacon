<?php

namespace Poing\Beacon;

use InvalidArgumentException;

class Beacon
{
    protected string $salt;
    protected string $domain;
    protected array  $algorithms;
    protected array  $aliases;
    protected int    $chunkSize;

    public function __construct()
    {
        $this->salt       = config('beacon.salt');
        $this->domain     = rtrim(config('beacon.domain'), '/');
        $this->algorithms = config('beacon.algorithms', [
            6  => 'md5',
            9  => 'sha1',
            12 => 'sha224',
            15 => 'sha256',
            18 => 'sha384',
            21 => 'sha512',
        ]);
        $this->aliases    = config('beacon.aliases', []);
        $this->chunkSize  = config('beacon.chunk_size', 4);
    }

    /**
     * Lookup algorithm name by config key.
     *
     * @throws InvalidArgumentException
     */
    protected function getAlgoName(int $key): string
    {
        if (! isset($this->algorithms[$key])) {
            throw new InvalidArgumentException("Unknown algorithm key {$key}");
        }
        return $this->algorithms[$key];
    }

    /**
     * Hash a timestamp using the selected algorithm.
     */
    public function generateHash(int $timestamp, int $algoKey = 15): string
    {
        $algo = $this->getAlgoName($algoKey);
        return hash($algo, $this->salt . (string) $timestamp);
    }

    /**
     * Split $hash into ($algoKey + 1) chunks of size $this->chunkSize.
     *
     * @return string[]
     */
    public function splitIntoChunks(string $hash, int $algoKey): array
    {
        $maxChunks = $algoKey + 1;
        $parts     = str_split($hash, $this->chunkSize);
        return array_slice($parts, 0, $maxChunks);
    }

    /**
     * Do we have any duplicate entries?
     */
    public function hasDuplicates(array $items): bool
    {
        return count($items) !== count(array_unique($items));
    }

    /**
     * Build a URL like: {domain}/{alias-or-algo}/{hexTimestamp}
     */
    public function generateUrl(int $timestamp, int $algoKey): string
    {
        $algo  = $this->getAlgoName($algoKey);
        $alias = $this->aliases[$algo] ?? $algo;
        $hex   = dechex($timestamp);

        return "{$this->domain}/{$alias}/{$hex}";
    }

    /**
     * 1) CONFIRMATION
     *
     * Always retries until collision-free, even if you passed in $timestamp.
     *
     * @return array{int, string, string[]}
     *         [ usedTimestamp, invitationUrl, array-of-codes ]
     */
    public function confirmation(int $algoKey = 15, ?int $timestamp = null): array
    {
        $ts = $timestamp ?: time();

        do {
            $hash  = $this->generateHash($ts, $algoKey);
            $codes = $this->splitIntoChunks($hash, $algoKey);
            $dups  = $this->hasDuplicates($codes);

            if ($dups) {
                # print_r($codes)
                usleep(1_000_000);
                $ts = time();
            }
        } while ($dups);

        $url = $this->generateUrl($ts, $algoKey);
        return [$ts, $url, $codes];
    }

    /**
     * 2) CONFIRM
     *
     * Returns the zero-based index of $code, or null.
     * If that timestamp *would* have collided, always null.
     */
    public function confirm(string $code, int $timestamp, int $algoKey = 15): ?int
    {
        $hash  = $this->generateHash($timestamp, $algoKey);
        $codes = $this->splitIntoChunks($hash, $algoKey);

        if ($this->hasDuplicates($codes)) {
            return null;
        }

        $pos = array_search($code, $codes, true);
        return $pos === false ? null : $pos;
    }



    /**
     * Find a number of hash collisions for each configured algorithm.
     *
     * This is used for testing. It generates hashes by incrementing timestamps
     * and looks for cases where the resulting hash chunks contain duplicates.
     *
     * A collision is when `splitIntoChunks()` produces repeated values, as
     * detected by `hasDuplicates()`. Chunks are sorted before storing.
     *
     * @param Beacon $beacon Instance of the Beacon class to use.
     * @param int $collisionsNeeded Number of collisions to find per algorithm.
     *
     * @return array<string, array{timestamp: int, hash: string, chunks: string[]}>
     *         Map of algorithm name to list of collision entries, each with the
     *         timestamp used, hash value, and sorted array of hash chunks.
     */
    public function findCollisionsForAllAlgorithms(
        Beacon $beacon,
        int $collisionsNeeded = 3
    ): array
    {
        $results = [];

        foreach ($beacon->algorithms as $key => $algo) {
            $collisions = [];
            $timestamp = time();

            // Search until we find the desired number of collisions
            while (count($collisions) < $collisionsNeeded) {
                $hash = $beacon->generateHash($timestamp, $key);
                $chunks = $beacon->splitIntoChunks($hash, $key);

                if ($beacon->hasDuplicates($chunks)) {
                    $sortedChunks = $chunks;
                    sort($sortedChunks); // or asort() if you want to preserve keys

                    $collisions[] = [
                        'timestamp' => $timestamp,
                        'hash' => $hash,
                        'chunks' => $sortedChunks,
                    ];
                }

                $timestamp++; // Try next second
            }

            $results[$algo] = $collisions;
        }

        return $results;
    }




}
