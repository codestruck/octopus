<?php

Octopus::loadClass('Octopus_Feed');

class Octopus_Feed_Rss extends Octopus_Feed {

    public function render($return = false) {

        $attributes = $this->renderAttributes();
        $items = $this->renderItems();


        $result = <<<END
<?xml version="1.0"?>
<rss version="2.0">
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
        return date('D, d M Y H:i:s O', $dt);
    }

    protected function renderItem(Octopus_Feed_Item $item) {

        $title = $item->getTitle();
        $description = $item->getDescription();
        $link = $item->getLink();
        $guid = $item->getGuid();
        $pubDate = $this->formatDate($item->getDate());

        if (!$guid) {
            $guid = $link;
        }

        $result = '<item>
';

        foreach(array('title', 'description', 'link', 'guid', 'pubDate') as $attr) {
            if ($$attr === null) {
                continue;
            }
            $value = h($$attr);
            $result .= "<$attr>$value</$attr>";
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

?>
