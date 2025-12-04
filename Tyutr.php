<?php
define("BOT_TOKEN","BOT_TOKEN"); //BOT TOKEN 
define("API_URL","https://api.telegram.org/bot".BOT_TOKEN."/");

// Data storage file path
define("DATA_FILE", "data.json");

$teachers = [
    "Pedagogika_fakulteti"=>[
        1=>["name"=>"FARIDA NAZAROVA","id"=>"4652973558"],
        2=>["name"=>"ULUG‘BEK ABDURAXMANOV","id"=>"849721863052"],
        3=>["name"=>"NARGIZA G‘OFUROVA","id"=>"34918697987"],
        4=>["name"=>"SADOQAT QODIROVA","id"=>"140099720517"],
        5=>["name"=>"SHOIRA ABDUSAMATOVA","id"=>"688029797457"],
    ],

    "Gumanitar_fanlar_va_jismoniy_madaniyat_fakulteti"=>[
        6=>["name"=>"NARGIZA MENNAZOVA","id"=>"18991251863"],
        7=>["name"=>"TIMUR AXMEDOV","id"=>"71636917566"],
        8=>["name"=>"JURABEK KURGANBAYEV","id"=>"19198758440"],
        9=>["name"=>"TURDIMUROT BAXRANOV","id"=>"691111493213"],
        10=>["name"=>"ASLIDDIN MAMATOV","id"=>"639149947876"],
        11=>["name"=>"UMIDJON MIRZALIYEV","id"=>"53491870482"],
        12=>["name"=>"RAVSHANBEK SULTANOV","id"=>"709113776562"],
    ],

    "Tillarni_o‘qitish_fakulteti"=>[
        13=>["name"=>"MOHINUR HAMROQULOVA","id"=>"891307084191"],
        14=>["name"=>"DILDORA TOJIBOYEVA","id"=>"3845391396"],
        15=>["name"=>"AZIMJON CHIMANOV","id"=>"61191980747"],
        16=>["name"=>"ALIMBEK SUYUNOV","id"=>"792918810108"],
        17=>["name"=>"NILUFAR BEKKUZIYEVA","id"=>"13912659325"],
        18=>["name"=>"DILSHOD SHERBEKOV","id"=>"146269151231"],
        19=>["name"=>"MAXFUZA SAMATOVA","id"=>"616387911535"],
    ],

    "Aniq_va_tabiiy_fanlar_fakulteti"=>[
        20=>["name"=>"ZUXRA ERGASHEVA","id"=>"584291269023"],
        21=>["name"=>"FARXOD SADIYEV","id"=>"87539102488"],
        22=>["name"=>"SOJIDA SAPAROVA","id"=>"695549188178"],
        23=>["name"=>"XASAN MAJIDOV","id"=>"125636591846"],
        24=>["name"=>"MAVJUDA ASHERALIYEVA","id"=>"609163551404"],
    ],

    "Qollab_quvvatlash"=>[
        25=>["name"=>"BOYIROV AKBAR","id"=>"820035910375"]
    ]
];


/* ==== DATA MANAGEMENT (Consolidated functions using data.json) ==== */

function loadData(){
    if(!file_exists(DATA_FILE)) return [];
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true) ?? [];
}

function saveData($data){
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// User submission state (which teacher user selected)
function getState($chat){ 
    $data = loadData();
    return $data[$chat]['state'] ?? null;
}
function setState($chat, $tid){ 
    $data = loadData();
    $data[$chat]['state'] = $tid;
    saveData($data);
}
function clearState($chat){ 
    $data = loadData();
    if (isset($data[$chat]['state'])) unset($data[$chat]['state']);
    saveData($data);
}

// User phone number
function getPhone($chat){ 
    $data = loadData();
    return $data[$chat]['phone'] ?? null;
}
function setPhone($chat, $phone){ 
    $data = loadData();
    $data[$chat]['phone'] = $phone;
    saveData($data);
}
function clearPhone($chat){ 
    $data = loadData();
    if (isset($data[$chat]['phone'])) unset($data[$chat]['phone']);
    saveData($data);
}

// Teacher Reply State (who the teacher is replying to)
function getReplyState($chat){ 
    $data = loadData();
    return $data[$chat]['reply_to'] ?? null;
}
function setReplyState($chat, $user_id){ 
    $data = loadData();
    $data[$chat]['reply_to'] = $user_id;
    saveData($data);
}
function clearReplyState($chat){ 
    $data = loadData();
    if (isset($data[$chat]['reply_to'])) unset($data[$chat]['reply_to']);
    saveData($data);
}


/* ==== TELEGRAM SEND ==== */
function send($method,$data){
    $ch=curl_init(API_URL.$method);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    $res=curl_exec($ch);
    curl_close($ch);
    return $res;
}

function edit($chat,$msg,$text,$keyboard=null){
    $data=["chat_id"=>$chat,"message_id"=>$msg,"text"=>$text,"parse_mode"=>"HTML"];
    if($keyboard) $data["reply_markup"]=json_encode(["inline_keyboard"=>$keyboard]);
    send("editMessageText",$data);
}


/* ==== UPDATE ==== */
$update=json_decode(file_get_contents("php://input"),true);

$chat_id=$update["message"]["chat"]["id"]??$update["callback_query"]["message"]["chat"]["id"];
$text=$update["message"]["text"]??"";
$callback=$update["callback_query"]["data"]??null;
$message_id=$update["message"]["message_id"]??$update["callback_query"]["message"]["message_id"]??null;
$contact=$update["message"]["contact"]["phone_number"]??null;


/* ==== START ==== */
if($text=="/start"){
    clearState($chat_id);
    clearPhone($chat_id);
    clearReplyState($chat_id); // Clear reply state too

    send("sendMessage",[
        "chat_id"=>$chat_id,
        "text"=>"👋 Assalomu alaykum!\nUshbu bot orqali <b>Guliston Davlat Pedagogika Instituti</b> tyutorlariga murojaat yuborishingiz mumkin.",
        "parse_mode"=>"HTML"
    ]);
    showFaculty($chat_id);
    exit;
}

// Check if the teacher wants to cancel replying using the new button text
if($text=="❌ Xabarni bekor qilish" && getReplyState($chat_id)){
    clearReplyState($chat_id);
    send("sendMessage",[
        "chat_id"=>$chat_id,
        "text"=>"❌ Javob berish bekor qilindi.",
        "reply_markup"=>json_encode(["remove_keyboard"=>true])
    ]);
    exit;
}


/* ==== CALLBACKS (FOR BOTH USER AND TEACHER) ==== */
if($callback){
    // User selects faculty
    if(strpos($callback,"fac_") === 0){
        $faculty = substr($callback,4);
        showTeachers($chat_id,$faculty,$message_id);
        exit;
    }

    // User selects teacher
    if(strpos($callback,"t_") === 0){
        $tid = substr($callback,2);
        setState($chat_id,$tid);

        $keyboard = [[["text"=>"📞 Telefon raqam yuborish","request_contact"=>true]]];

        send("sendMessage",[
            "chat_id"=>$chat_id,
            "text"=>"📞 Iltimos, telefon raqamingizni yuboring:",
            "reply_markup"=>json_encode(["keyboard"=>$keyboard,"resize_keyboard"=>true])
        ]);
        exit;
    }

    // User navigates back
    if($callback=="back"){
        showFaculty($chat_id,true,$message_id);
        exit;
    }

    // User cancels submission
    if($callback=="cancel"){
        clearState($chat_id);
        clearPhone($chat_id);
        send("sendMessage",["chat_id"=>$chat_id,"text"=>"❌ Xabar yuborish bekor qilindi."]);
        showFaculty($chat_id);
        exit;
    }
    
    // TEACHER initiates reply to a user
    if(strpos($callback,"reply_to_user_") === 0){
        $user_to_reply = substr($callback,14); // Extract user_id

        // Check if the chat_id is actually a teacher
        $is_teacher = false;
        foreach($teachers as $faculty_data){
            foreach($faculty_data as $t){
                if($t["id"] == $chat_id) $is_teacher = true;
            }
        }
        
        if($is_teacher){
            setReplyState($chat_id, $user_to_reply);

            // NEW: Updated keyboard text
            $keyboard = [[["text"=>"❌ Xabarni bekor qilish"]]];

            // We edit the original message (the one with 'Javob berish' button) to confirm reply mode
            edit($chat_id, $message_id, 
                "✅ Siz javob berish rejimidasiz. Javobingizni kiriting. \nJavob berishni bekor qilish uchun pastdagi tugmani bosing.",
                // Remove inline keyboard by passing null/empty array
            ); 
            
            // Send new message with reply keyboard to allow typing
            send("sendMessage",[
                "chat_id"=>$chat_id,
                "text"=>"Javob matnini kiriting:",
                "reply_markup"=>json_encode(["keyboard"=>$keyboard,"resize_keyboard"=>true])
            ]);
        } else {
             send("sendMessage",["chat_id"=>$chat_id,"text"=>"Siz bu amalni bajara olmaysiz."]);
        }
        exit;
    }
}


/* ==== TEACHER SENDS REPLY TO USER ==== */
$reply_to_user_id = getReplyState($chat_id);

if($reply_to_user_id && !empty($text) && $text!="❌ Xabarni bekor qilish"){
    
    // Check if the current chat is a teacher's chat (Optional but recommended security check)
    $is_teacher = false;
    $teacher_name = "Tyutor";
    foreach($teachers as $faculty_data){
        foreach($faculty_data as $t){
            if($t["id"] == $chat_id){
                $is_teacher = true;
                $teacher_name = $t["name"];
                break;
            }
        }
    }
    
    if($is_teacher){
        $time=date("d.m.Y H:i");
        
        send("sendMessage",[
            "chat_id"=>$reply_to_user_id,
            "text"=>"➡️ **$teacher_name** dan javob:\n\n$text\n\n🕒 $time",
            "parse_mode"=>"Markdown"
        ]);

        clearReplyState($chat_id);

        send("sendMessage",[
            "chat_id"=>$chat_id,
            "text"=>"✅ Javob foydalanuvchiga muvaffaqiyatli yuborildi.",
            "reply_markup"=>json_encode(["remove_keyboard"=>true]) // Remove reply keyboard
        ]);
        exit;
    }
}


/* ==== PHONE RECEIVED (USER) ==== */
$st=getState($chat_id);

if($st && $contact){
    setPhone($chat_id,$contact);
    send("sendMessage",[
        "chat_id"=>$chat_id,
        "text"=>"✍️ Endi o‘qituvchiga yuboriladigan xabarni kiriting:",
        "reply_markup"=>json_encode(["remove_keyboard"=>true])
    ]);
    exit;
}


/* ==== SEND MESSAGE TO TEACHER (USER SUBMISSION) ==== */
$phone=getPhone($chat_id);

if($st && $phone){
    if(empty($text)){
        send("sendMessage",["chat_id"=>$chat_id,"text"=>"❌ Iltimos faqat matn yuboring."]);
        exit;
    }

    $teacher_chat=null;
    foreach($teachers as $faculty_data){
        foreach($faculty_data as $id=>$t){
            if($id==$st) $teacher_chat=$t["id"];
        }
    }

    if($teacher_chat){
        $from=$update["message"]["from"];
        $name=trim(($from["first_name"]??"")." ".($from["last_name"]??""));
        $username=$from["username"]??"";
        $sender = $username ? "<a href='https://t.me/$username'>$name</a>" : $name;
        $time=date("d.m.Y H:i");
        
        // Inline button for teacher to reply
        $reply_button = [[["text"=>"✉️ Javob Berish","callback_data"=>"reply_to_user_".$chat_id]]];

        send("sendMessage",[
            "chat_id"=>$teacher_chat,
            "text"=>"📩 Sizga yangi xabar:\n\n$text\n\n👤 $sender\n📱 $phone\n🕒 $time",
            "parse_mode"=>"HTML",
            "reply_markup"=>json_encode(["inline_keyboard"=>$reply_button])
        ]);
    }

    clearState($chat_id);
    clearPhone($chat_id);

    send("sendMessage",["chat_id"=>$chat_id,"text"=>"✅ Xabar yuborildi!"]);
    showFaculty($chat_id);
    exit;
}


/* ==== ONLY TEXT WITHOUT STATE ==== */
if(!empty($text) && !$st && !$reply_to_user_id){
    send("sendMessage",["chat_id"=>$chat_id,"text"=>"❗ Iltimos, fakultetni tanlang:"]);
    showFaculty($chat_id);
    exit;
}



/* ==== FUNCTIONS ==== */
function showFaculty($chat,$edit=false,$msg=null){
    global $teachers;

    $btn=[];
    foreach($teachers as $name=>$t){
        $btn[]=[["text"=>str_replace("_"," ",$name),"callback_data"=>"fac_".$name]];
    }

    $data=[
        "chat_id"=>$chat,
        "text"=>"Fakultetni tanlang:",
        "reply_markup"=>json_encode(["inline_keyboard"=>$btn])
    ];

    if($edit){
        send("editMessageText",[
            "chat_id"=>$chat,
            "message_id"=>$msg,
            "text"=>"Fakultetni tanlang:",
            "reply_markup"=>json_encode(["inline_keyboard"=>$btn])
        ]);
    } else send("sendMessage",$data);
}

function showTeachers($chat,$faculty,$msg_id){
    global $teachers;

    $list=$teachers[$faculty];
    $btn=[];

    foreach($list as $id=>$t){
        $btn[]=[["text"=>$t["name"],"callback_data"=>"t_$id"]];
    }

    $btn[]=[["text"=>"◀️ Ortga","callback_data"=>"back"]];

    edit($chat,$msg_id,"O‘qituvchini tanlang:",$btn);
}
?>