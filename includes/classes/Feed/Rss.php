<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Feed_Rss extends Octopus_Feed {

    public function render($return = false) {

        $attributes = $this->renderAttributes();
        $items = $this->renderItems();


        $result = <<<END
<?xml version="1.0"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    $attributes
    $items
</channel>
</rss>
END;

        if ($return) return $result;

        echo $result;
    }

    protected function formatDate($dt) {

        if (!is_numeric($dt)) {
            $dt = strtotime($dt);
        }

        if (!$dt) return null;

        // Mon, 06 Sep 2010 00:01:00 +0000
        return date('r', $dt);
    }

    protected function renderItem(Octopus_Feed_Item $item) {

        $title = $item->getTitle();
        $description = $item->getDescription();
        $content = $item->getFullContent();
        $link = $item->getLink();
        $guid = $item->getGuid();
        $pubDate = $this->formatDate($item->getDate());
        $extra = $item->getExtra();

        if (!$guid) {
            $guid = $link;
        }

        $result = '<item>
';

        foreach(array('title', 'description', 'content', 'link', 'guid', 'pubDate') as $attr) {

            $value = $$attr;

            if ($value === null) {

                if ($attr !== 'content') {
                    continue;
                }

                $value = '';
            }

            if ($attr === 'description' || $attr === 'title' || $attr === 'content') {
                $value = "<![CDATA[$value]]>";
            }

            if ($attr === 'content') {
                $attr = 'content:encoded';
            }

            $attrs = '';
            if ($attr === 'guid') {
                if (!preg_match('#^(https?)?://.+#i', $value)) {
                    $attrs = ' isPermaLink="false"';
                }
            }

            $result .= "<{$attr}{$attrs}>$value</$attr>";
        }

        if ($extra) {
            foreach($extra as $key => $value) {
                $key = h($key);
                $value = h($value);
                $result .= "<$key>$value</$key>";
            }
        }

        $result .= '
</item>';

        return $result;

    }

    protected function renderItems() {

        $result = '';

        foreach($this->getItems() as $item) {

            $result .= "\n" . $this->renderItem($item);

        }

        return $result;

    }

    protected function renderAttributes() {

        $result = '';

        foreach(array('title', 'description', 'link', 'lastBuildDate', 'pubDate') as $attr) {

            $value = $this->getOption($attr, null);

            if ($value === null) {
                continue;
            }

            if (preg_match('/Date$/', $attr)) {
                $value = $this->formatDate($value);
            }

            $value = h($value);

                $result .= <<<END

<$attr>$value</$attr>
END;
        }

        return $result;
    }

}

