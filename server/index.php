<?
 //
phpinfo();


$dbms='mysql';     //���ݿ�����
$host='127.0.0.1'; //���ݿ�������
$dbName='test';    //ʹ�õ����ݿ�
$user='root';      //���ݿ������û���
$pass='';          //��Ӧ������
$dsn="$dbms:host=$host;port=3310;dbname=$dbName";


try {
    $dbh = new PDO($dsn, $user, $pass); //��ʼ��һ��PDO����
    echo "���ӳɹ�<br/>";
    /*�㻹���Խ���һ����������
    foreach ($dbh->query('SELECT * from FOO') as $row) {
        print_r($row); //������� echo($GLOBAL); ��������Щֵ
    }
    */
    $dbh = null;
} catch (PDOException $e) {
    die ("Error!: " . $e->getMessage() . "<br/>");
}
//Ĭ��������ǳ����ӣ������Ҫ���ݿⳤ���ӣ���Ҫ����һ��������array(PDO::ATTR_PERSISTENT => true) ���������
$db = new PDO($dsn, $user, $pass, array(PDO::ATTR_PERSISTENT => true));