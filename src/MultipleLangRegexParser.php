<?php

declare(strict_types=1);

namespace KHTMultiRegexParser;

use DateTime;
use KindleHighlightTosser\Application\Clipping;
use KindleHighlightTosser\Infrastructure\Support\Bom;
use KindleHighlightTosser\Infrastructure\Parser\MyClippings\RawClippingParser;
use KindleHighlightTosser\Infrastructure\Parser\MyClippings\ParsedRawClipping;
use KindleHighlightTosser\Infrastructure\Parser\MyClippings\CouldNotParseRawClipping;

class MultipleLangRegexParser implements RawClippingParser
{
    private const FIX_PARTS_REGEX = '/((.+)\((.+)?\)\r*\n.+?|(.+)\r*\n.+?)\s([0-9]+)\s([0-9]+):([0-9]+):([0-9]+)\s*(AM|PM)*[\r|\n\r|\n]*(.*)/';
    private const SECOND_LINE_REGEX = '/[\r|\n\r|\n]*(.*)/';
    private const LOCATION_REGEX = '/\s(?|([0-9]+)\-([0-9]+)|([0-9]+)\st\/m\s([0-9]+))\s[|]/';
    private const MONTH_DAY_REGEX = '/[|]\s.*(?|\s(\d+)\.?\s(\w{3,})|\s(\w+)\s(\d+),|\s(\d+)\s\w{2}\s(\w+))/';
    private const HIGHLIGHT_TRANSLATIONS_REGEX = '(surlignement|subrayado|evidenziazione|Highlight|Markierung|destaque|highlight)';
    const HIGHLIGHT = "highlight";
    const MONTHS_TRANSLATED = [
        ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
        ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'],
        ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
        ['Januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
        ['januar', 'februar', 'märz', 'april', 'mai', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'dezember'],
        ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']
    ];
    const MONTHS_KEY = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    private $year;
    private $month;
    private $day;
    private $hour;
    private $minute;
    private $second;

    public function execute(string $clippingContent): Clipping
    {
        preg_match_all(self::FIX_PARTS_REGEX, $clippingContent, $matches);

        if (empty($matches[0])) {
            throw new CouldNotParseRawClipping();
        }

        preg_match_all(self::SECOND_LINE_REGEX, $clippingContent, $matchesLine);
        $isHighlight = preg_match(self::HIGHLIGHT_TRANSLATIONS_REGEX,$matchesLine[0][1]);
        preg_match_all(self::LOCATION_REGEX, $matchesLine[0][1], $location);
        preg_match_all(self::MONTH_DAY_REGEX, $matchesLine[0][1], $monthDay);
        $type = $isHighlight ? self::HIGHLIGHT : 'other';
        $publicationTitle = !empty($matches[2][0])?$matches[2][0]: $matches[4][0];
        $publicationAuthor = !empty($matches[3][0])?$matches[3][0]: "unknown";
        $publicationTitle = Bom::remove($publicationTitle);
        $locationFrom = $location[1][0];
        $locationTo = $location[2][0] ?? $locationFrom;
        $this->year = $matches[5][0];
        $this->setMonth($monthDay);
        $this->setDay($monthDay);
        $this->hour = $matches[6][0];
        $dateTimeHourType = $matches[9][0]??false;
        $this->minute = $matches[7][0];
        $this->second = $matches[8][0];
        $dateTime = $this->formDate($dateTimeHourType);
        if (!$dateTime) {
            throw new CouldNotParseRawClipping();
        }

        $content = Bom::remove($matches[10][0]);

        return new Clipping(
            $type,
            $content,
            $publicationTitle,
            $publicationAuthor,
            (int)$locationFrom,
            (int)$locationTo,
            $dateTime->format('U')
        );
    }

    /**
     * Translates the month name to english so can be accepted by DateTime
     * @param string $month
     * @return string
     */
    private function translateMonth(string $month):string
    {
        $monthsEnglish = self::MONTHS_KEY;
        $translatedMonth = $monthsEnglish[0];
        $monthsTranslations = self::MONTHS_TRANSLATED;

        foreach($monthsTranslations as $translation){
            $month = strtolower($month);
            $foundInTranslation = array_search($month,$translation);
            if($foundInTranslation){
                $translatedMonth = $monthsEnglish[$foundInTranslation];
                continue;
            }
        }
        return $translatedMonth;
    }

    /**
     * Forms the DateTime object depending on the hour type is sent (12h/24h)
     * @param string $dateTimeHourType
     * @return DateTime|false
     */
    private function formDate(string $dateTimeHourType):DateTime
    {
        return $dateTimeHourType ? DateTime::createFromFormat(
            'Y F j g i s A',
            sprintf(
                '%s %s %s %s %s %s %s',
                $this->year,
                $this->month,
                $this->day,
                $this->hour,
                $this->minute,
                $this->second,
                $dateTimeHourType
            )
        ) : DateTime::createFromFormat(
            'Y F j G i s',
            sprintf(
                '%s %s %s %s %s %s',
                $this->year,
                $this->month,
                $this->day,
                $this->hour,
                $this->minute,
                $this->second
            )
        );
    }

    /**
     * Sets the month taking it from the right place, some translations misplace it
     * @param array $monthDay
     */
    private function setMonth(array $monthDay): void
    {
        $this->month = strlen($monthDay[1][0]) > 2 ? $this->translateMonth($monthDay[1][0]) : $this->translateMonth($monthDay[2][0]);
    }

    /**
     * Sets the day taking it from the right place, some translations misplace it
     * @param array $monthDay
     */
    private function setDay(array $monthDay): void
    {
        $this->day = strlen($monthDay[1][0]) > 2 ? $monthDay[2][0] : $monthDay[1][0];
    }
}
