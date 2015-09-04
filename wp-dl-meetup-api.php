<?php

/*

http://api.meetup.com/2/events?status=upcoming&order=time&limited_events=False&group_urlname=DataKind-NYC&desc=false&offset=0&photo-host=public&format=json&page=20&fields=&sig_id=183808390&sig=72895b288f1d8ca6d906d8835fc93e9434364c70

http://api.meetup.com/2/groups?radius=25.0&order=id&group_urlname=DataKind-NYC&desc=false&offset=0&photo-host=public&format=json&page=20&fields=&sig_id=183808390&sig=f78349aa685475e5510e0cef5e88844441ab7b09

*/

if (!class_exists('DatalookMeetupAPi')) {

    /*
     *  TODO
     *
     *  - clean up: create view fo rendering from controller
     *  - clean up: now is very specific for members / events should be more generalist
     *  - downgrade if meetup api limit api reached
     *  - mock up table on client side for allowing reordering
     *  - add custom field on Admin panel for adding group ids
     *  - add custom field on Admin panel for api / key
     *  - Add on github (as plugin)
     *
     *  http://www.meetup.com/DataforGood/ 3926102
        http://www.meetup.com/DataKind-NYC/" 4300032
        http://www.meetup.com/DataKind-UK/" 7975692
        http://www.meetup.com/Data-for-Good-Calgary/" 11057822
        http://www.meetup.com/DataforGood-Montreal/ 11073962
        http://www.meetup.com/DataKind-DUB/ 11120692
        http://www.meetup.com/Brussels-Data-Science-Community-Meetup/ 12977072
        http://www.meetup.com/DataKind-DC/ 16394282
        http://www.meetup.com/DataKind-SG/ 16412132
        http://www.meetup.com/DataKind-Bangalore/ 16412292
        http://www.meetup.com/Data-for-Good-FR/ 18259255
     */
    class DatalookMeetupAPi
    {

        const DOMAIN = "api.meetup.com";
        const METHOD_GROUPS = "2/groups";
        const METHOD_EVENTS = "2/events";

        //@Deprecated use $_key dynamic private attribute instead.
        const KEY = "6172502d70155707241395b42137b45";
        //@Deprecated use $_groups_ids dynamic private attribute instead.
        const ID_GROUPS = "3926102,4300032,7975692,11057822,11073962,11120692,12977072,16394282,16412132,16412292,18259255";

        private $_url;
        private $_results;
        private $_logs;
        private $_key;
        private $_groups_ids;

        public function __construct()
        {
            add_action('init', array(&$this, 'getBoardInfo'));
            #   add_action( 'wp_enqueue_scripts', array(&$this, 'enqueueScript') );
            #   add_action('init', array(&$this, 'log2'));
            add_shortcode('render_table_meetup_datalook', array(&$this, 'renderTable'));

            $this->api_key = get_option("api_key");
            $this->groups_ids = get_option("groups_ids");
            $this->dynamicContent = true;

        }

        public function log2()
        {
            echo $this->getUrl(SELF::METHOD_GROUPS, "&group_id=" . urlencode($this->groups_ids));
        }

        public function enqueueScript()
        {
            wp_register_script('custom-script', plugins_url( '/js/custom-script.js', __FILE__ ));
            wp_enqueue_script( 'custom-script' );
        }

        private function getUrl($method, $params)
        {
            return "http://" . SELF::DOMAIN . DIRECTORY_SEPARATOR . $method .
            DIRECTORY_SEPARATOR . "?key=" . $this->api_key . $params;
        }

        private function getNumberOfEvents() {

            $url_events = $this->getUrl(SELF::METHOD_EVENTS, "&status=past&group_id=" . urlencode($this->groups_ids));
            $info_events = wp_remote_get($url_events);
            $events = json_decode($info_events["body"])->results;

            $result = [];
            foreach ($events as $item) {
                $result[$item->group->id] = $result[$item->group->id] + 1;
            }

            return $result;

        }


        public function getBoardInfo()
        {
            $url_groups = $this->getUrl(SELF::METHOD_GROUPS, "&group_id=" . urlencode($this->groups_ids));
            $info_groups = wp_remote_get($url_groups);
            if ($info_groups["response"][code] != 200) {

            } else {
                $groups = json_decode($info_groups["body"])->results;
                $events = $this->getNumberOfEvents();

                foreach ($groups as $item) {

                    $tmpArray["groups_name"] = $item->name;
                    $tmpArray["groups_link"] = $item->link;
                    $tmpArray["city"] = $item->city;
                    $tmpArray["country"] = $item->country;

                    $mil = $item->created;
                    $seconds = $mil / 1000;
                    $year = date("Y", $seconds);

                    $tmpArray["groups_year_created"] = $year;
                    $tmpArray["groups_members"] = $item->members;

                    $tmpArray["events_number"] = 0;
                    if ($events[$item->id] != null) {
                        $tmpArray["events_number"] = $events[$item->id];
                    }

                    $this->results[] = $tmpArray;

                }
            }
        }

        public function renderTable()
        {
            if ($this->dynamicContent && !empty($this->results)) {

                $html = "<table><th style=\"text-align: left; width:40%;\">Name</th>
                    <th style=\"text-align: center; width:20%;\">Creation year</th>
                    <th style=\"text-align: center; width:10%;\">Members</th>
                    <th style=\"text-align: center; width:15%;\">Past events</th>
                    <th style=\"text-align: center; width:15%;\">Location</th>";
                foreach ( $this->results as $item ) {

                    $html .= "<tr>
          <td style=\"text-align: left; width:35%;\"><a href=\"" . $item["groups_link"] . "\" target=\"_blank\">" . $item["groups_name"] . "</td>
          <td style=\"text-align: center; width:20%;\">" . $item["groups_year_created"] . "</td>
          <td style=\"text-align: center; width:10%;\">" . $item["groups_members"] . "</td>
          <td style=\"text-align: center; width:15%;\"> " . $item["events_number"] . "</td>
          <td style=\"text-align: center; width:15%;\"> " . $item["city"] . "</td></tr>";
                }
                $html = $html . "</table>";
            } else {
                //if for any reason we can not retrieve data dynamically a
                $html = '<table>
                        <tbody>
                        <tr>
                        <th style="text-align: left; width: 40%;">Name</th>
                        <th style="text-align: center; width: 20%;">Creation year</th>
                        <th style="text-align: center; width: 10%;">Members</th>
                        <th style="text-align: center; width: 15%;">Past events</th>
                        <th style="text-align: center; width: 15%;">Location</th>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataforGood/" target="_blank">Data for Good - Data Scientists &amp; Devs doing GOOD</a></td>
                        <td style="text-align: center; width: 20%;">2012</td>
                        <td style="text-align: center; width: 10%;">662</td>
                        <td style="text-align: center; width: 15%;">13</td>
                        <td style="text-align: center; width: 15%;">Toronto</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataKind-NYC/" target="_blank">DataKind NYC</a></td>
                        <td style="text-align: center; width: 20%;">2012</td>
                        <td style="text-align: center; width: 10%;">2043</td>
                        <td style="text-align: center; width: 15%;">22</td>
                        <td style="text-align: center; width: 15%;">New York</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataKind-UK/" target="_blank">DataKind UK</a></td>
                        <td style="text-align: center; width: 20%;">2013</td>
                        <td style="text-align: center; width: 10%;">1289</td>
                        <td style="text-align: center; width: 15%;">9</td>
                        <td style="text-align: center; width: 15%;">London</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/Data-for-Good-Calgary/" target="_blank">Data for Good - Calgary</a></td>
                        <td style="text-align: center; width: 20%;">2013</td>
                        <td style="text-align: center; width: 10%;">357</td>
                        <td style="text-align: center; width: 15%;">14</td>
                        <td style="text-align: center; width: 15%;">Calgary</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataforGood-Montreal/" target="_blank">Data for Good Montreal - Data Scientists &amp; Devs doing GOOD</a></td>
                        <td style="text-align: center; width: 20%;">2013</td>
                        <td style="text-align: center; width: 10%;">140</td>
                        <td style="text-align: center; width: 15%;">1</td>
                        <td style="text-align: center; width: 15%;">Montréal</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataKind-DUB/" target="_blank">DataKind Dublin</a></td>
                        <td style="text-align: center; width: 20%;">2013</td>
                        <td style="text-align: center; width: 10%;">483</td>
                        <td style="text-align: center; width: 15%;">15</td>
                        <td style="text-align: center; width: 15%;">Dublin</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/Brussels-Data-Science-Community-Meetup/" target="_blank">Brussels Data Science Meetup</a></td>
                        <td style="text-align: center; width: 20%;">2014</td>
                        <td style="text-align: center; width: 10%;">1280</td>
                        <td style="text-align: center; width: 15%;">35</td>
                        <td style="text-align: center; width: 15%;">Brussels</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataKind-DC/" target="_blank">DataKind DC</a></td>
                        <td style="text-align: center; width: 20%;">2014</td>
                        <td style="text-align: center; width: 10%;">617</td>
                        <td style="text-align: center; width: 15%;">5</td>
                        <td style="text-align: center; width: 15%;">Washington</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataKind-SG/" target="_blank">DataKind SG</a></td>
                        <td style="text-align: center; width: 20%;">2014</td>
                        <td style="text-align: center; width: 10%;">717</td>
                        <td style="text-align: center; width: 15%;">9</td>
                        <td style="text-align: center; width: 15%;">Singapore</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/DataKind-Bangalore/" target="_blank">DataKind Bangalore</a></td>
                        <td style="text-align: center; width: 20%;">2014</td>
                        <td style="text-align: center; width: 10%;">502</td>
                        <td style="text-align: center; width: 15%;">7</td>
                        <td style="text-align: center; width: 15%;">Bangalore</td>
                        </tr>
                        <tr>
                        <td style="text-align: left; width: 35%;"><a href="http://www.meetup.com/Data-for-Good-FR/" target="_blank">Data for Good</a></td>
                        <td style="text-align: center; width: 20%;">2014</td>
                        <td style="text-align: center; width: 10%;">588</td>
                        <td style="text-align: center; width: 15%;">2</td>
                        <td style="text-align: center; width: 15%;">Paris</td>
                        </tr>
                        </tbody>
                </table>';
            }

            return $html;

        }
    }

}
