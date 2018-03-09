<link rel="stylesheet" href="css/style.css">

<?php
require("header.php");
require("lib/mysql.php");

header("content-type:text/html; charset=utf-8");

// Get user roles
$g_users = array();
$result = getQuery("SELECT users.id, roles.name as role, users.name, users.cname FROM roles INNER JOIN users ON users.role=roles.id");
while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $name = $line['cname'].' '. $line['name'];
    $roleName = $line['role'];
    if (strlen($roleName) > 0) {
        $g_users[$line['id']] = "$name ($roleName)";
    } else {
        $g_users[$line['id']] = $name;
    }
    //echo $line['id'].'>>'.$g_users[$line['id']]."<br>";
}

function getLeaderId($group)
{
    $group = mysql_real_escape_string($group);
    $result = mysql_query("select id from users where `group`=$group and role in (0,1,2,3,4,6,7)") or die('Query failed: ' . mysql_error());
    $row = mysql_fetch_row($result);
    return $row[0];
}

function getMembers($class, $group)
{
    global $g_users;
    $members = array();
    $class = mysql_real_escape_string($class);
    $group = mysql_real_escape_string($group);
    if ($group == 0) {
        $result = getQuery("select id from users where class=$class and role NOT IN (6,255) order by role, cname asc");
    } else {
        $result = getQuery("select id from users where class=$class and `group`=$group order by role, cname asc");
    }
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $members[$line['id']] = $g_users[$line['id']];
    }
    return $members;
}


function showLeaderMeetingAttendance()
{
    global $class;
    global $g_users;
    echo "<h4>同工小组LM(周六)<br>";

    $result = getQuery("select id from users where class=$class and role != 255 order by role, cname asc");
    $members = array();
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $members[$line['id']] = $g_users[$line['id']];
    }
    mysql_free_result($result);

    $result = getQuery("select * from attendanceLeadersMeetingDates where class=$class order by date asc");
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $date = mysql_real_escape_string($line["date"]);
        // find attendance by group
        $row = getRow("select users, totalUsers from attendance where `date`='$date' order by submitDate desc limit 1");
        if ($row === false) {
            $attend[$date] = "";
            $totalUsers[$date] = 0;
        } else {
            if ($row[0] == "[]") {
                $attend[$date] = "";
            } else {
                $attend[$date] = substr($row[0], 1, -1).',';
            }
            $totalUsers[$date] = $row[1];
        }
        //echo "$date => $attend[$date] totalUsers: $totalUsers[$date]<br>";
    }
    mysql_free_result($result);

    echo "<table border=1><tr><td style='min-width: 240px;'>";
    $idx = 0;
    while (list($date, $users) = each($attend)) {
        list($year, $month, $day) = split('[/.-]', $date);
        echo "<td width='30' align='center'>#$idx<br>$month/$day";
        $idx++;
    }

    reset($members);
    $id = 1;
    while (list($userId, $name) = each($members)) {
        echo "<tr><td>$id. $name";// (#$userId)";
        reset($attend);
        while (list($date, $users) = each($attend)) {
            if (strpos($users, $userId.',') === false) {
                $check = ' ';
            } else {
                $check = '√';
            }

            echo "<td align='center'>$check";
        }
        $id++;
    }

    echo "<tr><td>出席总人数:";
    reset($attend);
    while (list($date, $users) = each($attend)) {
        $count = substr_count($users, ',');
        echo "<td align='center'>$count";
    }

    $totalCountByDate = array();
    echo "<tr><td>注册总人数:";
    reset($attend);
    while (list($date, $users) = each($attend)) {
        $count = $totalUsers[$date];
        $totalCountByDate[$date] = $count;
        echo "<td align='center'>$count";
    }

    echo "<tr><td>(%)百分比:";
    reset($attend);
    while (list($date, $users) = each($attend)) {
        $count = substr_count($users, ',');
        $totalCount = $totalCountByDate[$date];
        if ($totalCount == 0) {
            $percent = 0;
        } else {
            $percent = round($count / $totalCount * 100);
        }
        echo "<td align='center'>$percent%";
    }

    echo "</table>";
}

// TODO: Support different class
$class = 1;
$row = getRow("select name from class where id=$class");
echo "<h3>出席表 Class: $row[0]</h3>";

showLeaderMeetingAttendance();


$classAttend = array();
$classTotal = array();

$query = "select distinct `group` from users where class=$class and `group` < 100 order by `group` asc";
$result = mysql_query($query) or die('Query failed: ' . mysql_error());
while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $group = mysql_real_escape_string($line["group"]);
    if ($group == 0) {
        echo "<h4>同工小组(周一)<br>";
    } elseif ($group < 100) {
        echo "<h4>成人小组#".$group."<br>";
    }
    $leaderId = getLeaderId($group);
    
    // get all member names
    $members = getMembers($class, $group);

    $attend = array();
    $totalUsers = array();
    $result2 = getQuery("select * from attendanceDates where class=$class order by date asc");
    while ($line = mysql_fetch_array($result2, MYSQL_ASSOC)) {
        $date = mysql_real_escape_string($line["date"]);
        // find attendance by group
        $row = getRow("select users, totalUsers from attendance where `date`='$date' and leader in (select leader from attendanceLeaders where `group`=$group) order by submitDate desc limit 1");
        if ($row === false) {
            $attend[$date] = "";
            $totalUsers[$date] = 0;
        } else {
            if ($row[0] == "[]") {
                $attend[$date] = "";
            } else {
                $attend[$date] = substr($row[0], 1, -1).',';
            }
            $totalUsers[$date] = $row[1];
        }
        //echo "$date => $attend[$date] totalUsers: $totalUsers[$date]<br>";
    }

    echo "<table border=1><tr><td style='min-width: 240px;'>";
    $idx = 0;
    while (list($date, $users) = each($attend)) {
        list($year, $month, $day) = split('[/.-]', $date);
        echo "<td width='30' align='center'>#$idx<br>$month/$day";
        $idx++;
    }

    reset($members);
    $id = 1;
    while (list($userId, $name) = each($members)) {
        echo "<tr><td>$id. $name";// (#$userId)";
        reset($attend);
        while (list($date, $users) = each($attend)) {
            if (strpos($users, $userId.',') === false) {
                $check = ' ';
            } else {
                $check = '√';
            }
            
            echo "<td align='center'>$check";
        }
        $id++;
    }

    echo "<tr><td>出席总人数:";
    reset($attend);
    while (list($date, $users) = each($attend)) {
        $count = substr_count($users, ',');
        $total = (isset($classAttend[$date])? $classAttend[$date] : 0) + $count;
        $classAttend[$date] = $total;
        echo "<td align='center'>$count";
    }

    $totalCountByDate = array();
    echo "<tr><td>注册总人数:";
    reset($attend);
    while (list($date, $users) = each($attend)) {
        $count = $totalUsers[$date];
        $totalCountByDate[$date] = $count;
        $total = (isset($classTotal[$date])? $classTotal[$date] : 0) + $count;
        $classTotal[$date] = $total;
        echo "<td align='center'>$count";
    }

    echo "<tr><td>(%)百分比:";
    reset($attend);
    while (list($date, $users) = each($attend)) {
        $count = substr_count($users, ',');
        $totalCount = $totalCountByDate[$date];
        if ($totalCount == 0) {
            $percent = 0;
        } else {
            $percent = round($count / $totalCount * 100);
        }
        echo "<td align='center'>$percent%";
    }

    echo "</table>";
}
mysql_free_result($result);

echo "<br><br><b>全班成人总计</b>";
echo "<table border=1><tr><td style='min-width: 240px;'>";
$idx = 0;
reset($classAttend);
while (list($date, $count) = each($classAttend)) {
    list($year, $month, $day) = split('[/.-]', $date);
    echo "<td width='30' align='center'>#$idx<br>$month/$day";
    $idx++;
}

echo "<tr><td>出席总人数:";
reset($classAttend);
while (list($date, $count) = each($classAttend)) {
    $count = $classAttend[$date];
    echo "<td align='center'>$count";
}

echo "<tr><td>注册总人数:";
reset($classTotal);
while (list($date, $count) = each($classTotal)) {
    $count = $classTotal[$date];
    echo "<td align='center'>$count";
}

echo "<tr><td>(%)百分比:";
reset($classAttend);
while (list($date, $count) = each($classAttend)) {
    $total = isset($classTotal[$date])? $classTotal[$date]: 0;
    if ($total == 0) {
        $percent = 0;
    } else {
        $percent = round($count / $total * 100);
    }
    echo "<td align='center'>$percent%";
}

echo "</table>";
require("footer.php");
?>