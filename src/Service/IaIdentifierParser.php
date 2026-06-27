<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

class IaIdentifierParser
{
    public function parse(string $line): ?string
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        if (preg_match('~^https?://archive\.org/details/([^?#]+)~i', $line, $m)) {
            return IaPath::normalize(rawurldecode($m[1]));
        }
        if (preg_match('~^https?://archive\.org/embed/([^?#]+)~i', $line, $m)) {
            return IaPath::normalize(rawurldecode($m[1]));
        }
        if (preg_match('~^https?://archive\.org/metadata/([^?#]+)~i', $line, $m)) {
            return IaPath::normalize(rawurldecode($m[1]));
        }
        if (preg_match('~^https?://archive\.org/download/([^/?#]+)/~i', $line, $m)) {
            return IaPath::normalize(rawurldecode($m[1]));
        }
        if (preg_match('~\s~', $line)) {
            return null;
        }

        return IaPath::normalize($line);
    }

    /**
     * @param string $text Multiline identifiers or URLs
     * @return string[]
     */
    public function parseLines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $id = $this->parse(trim($line));
            if ($id) {
                $out[] = $id;
            }
        }
        return $out;
    }
}
