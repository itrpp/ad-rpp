<!DOCTYPE html>
<html>
<head>
    <title>LDAP Management</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
            </ul>
        </nav>

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <a href="index.php" class="brand-link">
                <span class="brand-text font-weight-light">LDAP Management</span>
            </a>

            <div class="sidebar">
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                        <li class="nav-item">
                            <a href="create.php" class="nav-link active">
                                <i class="nav-icon fas fa-user-plus"></i>
                                <p>สร้าง/แก้ไข/ลบผู้ใช้</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="full.php" class="nav-link">
                                <i class="nav-icon fas fa-user"></i>
                                <p>ดูข้อมูลผู้ใช้</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php" class="nav-link">
                                <i class="nav-icon fas fa-users"></i>
                                <p>ดูข้อมูลผู้ใช้ทั้งหมด</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="updateAD.php" class="nav-link">
                                <i class="nav-icon fas fa-sync"></i>
                                <p>อัปเดต AD</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1>จัดการผู้ใช้ LDAP</h1>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">ฟอร์มจัดการผู้ใช้</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="form-group">
                                            <label for="samaccountname">User Name</label>
                                            <input type="text" class="form-control" id="samaccountname" name="samaccountname" placeholder="ใช้งานเป็นชื่อผู้ใช้" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="username">ชื่อผู้ใช้</label>
                                            <input type="text" class="form-control" id="username" name="username" placeholder="ชื่อผู้ใช้" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="sername">นามสกุล</label>
                                            <input type="text" class="form-control" id="sername" name="sername" placeholder="นามสกุล" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email">อีเมล</label>
                                            <input type="email" class="form-control" id="email" name="email" placeholder="อีเมล">
                                        </div>
                                        <div class="form-group">
                                            <label for="department">แผนก</label>
                                            <input type="text" class="form-control" id="department" name="department" placeholder="แผนก">
                                        </div>
                                        <div class="form-group">
                                            <label for="telephone">เบอร์โทรศัพท์</label>
                                            <input type="text" class="form-control" id="telephone" name="telephone" placeholder="เบอร์โทรศัพท์">
                                        </div>
                                        <!-- <div class="form-group">
                                            <label for="password">รหัสผ่าน</label>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="รหัสผ่าน" required>
                                        </div> -->
                                        <div class="form-group">
                                            <button type="submit" name="action" value="Create" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> สร้างผู้ใช้
                                            </button>
                                            <button type="submit" name="action" value="Update" class="btn btn-warning">
                                                <i class="fas fa-edit"></i> แก้ไขข้อมูล
                                            </button>
                                            <button type="submit" name="action" value="Delete" class="btn btn-danger">
                                                <i class="fas fa-trash"></i> ลบผู้ใช้
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <footer class="main-footer">
            <div class="float-right d-none d-sm-block">
                <b>Version</b> 1.0.0
            </div>
            <strong>Copyright &copy; 2024</strong>
        </footer>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>

<?php
// 🔹 กำหนดค่าการเชื่อมต่อ LDAP
$ldap_host = "ldap://192.168.238.8:389"; // เปลี่ยนเป็นเซิร์ฟเวอร์ AD ของคุณ
$ldap_port = 389; // ค่าเริ่มต้นของ LDAP Port (LDAP ใช้ 389, LDAPS ใช้ 636)
$ldap_user = "cn=ldaprpp,OU=rpp-user,DC=rpphosp,DC=local";
$ldap_password = "rpp14641"; // รหัสผ่าน
$ldap_base_dn  = "DC=rpphosp,DC=local"; 

// 🔹 เชื่อมต่อไปยัง LDAP
$ldap_conn = ldap_connect($ldap_host, $ldap_port);
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

// 🔹 ตรวจสอบการล็อกอิน
if (@ldap_bind($ldap_conn, $ldap_user, $ldap_password)) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'] ?? '';
        $sername = $_POST['sername'] ?? ''; 
        $cn = $username.$sername;
        $samaccountname = $_POST['samaccountname'] ?? '';
        $userPrincipalName = $samaccountname . '@rpphosp.local';
        $email = $_POST['email'] ?? '';
        $department = $_POST['department'] ?? '';
        $telephone = $_POST['telephone'] ?? '';
        $password = $_POST['password'] ?? '123456';
        $action = $_POST['action'] ?? '';
        $dn = "cn=".str_replace(" ", "", $username.$sername).",ou=Register,ou=rpp-user,$ldap_base_dn";

        if ($action == "Create") {
            // ตรวจสอบ sAMAccountName ซ้ำ
            $filter = "(sAMAccountName=$samaccountname)";
            $result = ldap_search($ldap_conn, $ldap_base_dn, $filter);
            $entries = ldap_get_entries($ldap_conn, $result);
            
            if ($entries['count'] > 0) {
                echo "❌ ไม่สามารถสร้างผู้ใช้ได้: sAMAccountName '$samaccountname' มีอยู่ในระบบแล้ว";
            } else {
                // 🔹 เพิ่มผู้ใช้ใหม่
                $entry = [
                    "cn" => $cn,
                    "sn" => $sername,
                    "mail" => $email,
                    "objectClass" => ["top", "person", "organizationalPerson", "user"],
                    "department" => $department,
                    "telephoneNumber" => $telephone,
                    "sAMAccountName" => $samaccountname,
                    "userPrincipalName" => $userPrincipalName,
                    "displayName" => $username." ".$sername,
                    "givenName" => $username,
                    "userPassword" => "{SHA}" . base64_encode(sha1($password, true)),
                    "pwdLastSet" => -1,
                   // "userAccountControl" => 66048
                ];
                if (ldap_add($ldap_conn, $dn, $entry)) {
                    echo "✅ เพิ่มผู้ใช้สำเร็จ!";
                } else {
                    echo "❌ เพิ่มผู้ใช้ล้มเหลว: " . ldap_error($ldap_conn);
                }
            }
        }

        if ($action == "Update") {
            // 🔹 อัปเดตข้อมูลผู้ใช้
            $entry = ["mail" => $email, "department" => $department];
            if (ldap_modify($ldap_conn, $dn, $entry)) {
                echo "✅ อัปเดตข้อมูลสำเร็จ!";
            } else {
                echo "❌ อัปเดตข้อมูลล้มเหลว: " . ldap_error($ldap_conn);
            }
        }

        if ($action == "Delete") {
            // 🔹 ลบผู้ใช้
            if (ldap_delete($ldap_conn, $dn)) {
                echo "✅ ลบผู้ใช้สำเร็จ!";
            } else {
                echo "❌ ลบผู้ใช้ล้มเหลว: " . ldap_error($ldap_conn);
            }
        }
    }
} else {
    echo "❌ ไม่สามารถเชื่อมต่อ LDAP: " . ldap_error($ldap_conn);
}

// 🔹 ปิดการเชื่อมต่อ
ldap_unbind($ldap_conn);
?>
