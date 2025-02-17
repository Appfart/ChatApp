<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BatchUserController extends Controller
{
    public function showBatchCreateForm()
    {
        $users = User::all(['id', 'name', 'referral_link', 'realname']); // Fetch existing users for referral dropdown
        return view('pages.app.batch_create_users', compact('users'));
    }

    public function createBatchUsers(Request $request)
    {
        $request->validate([
            'user_count' => 'required|integer|min:1',
            'referral' => 'required|exists:users,id',
            'password' => 'required|string|min:6',
            'security_pin' => 'required|digits:6',
        ]);
    
        $userCount = $request->user_count;
        $referralId = $request->referral;
        $realPassword = $request->password; // Get the real password
        $hashedPassword = Hash::make($realPassword); // Hash the password
        $securityPin = $request->security_pin;
    
        $users = [];
        $referralUser = User::find($referralId);
    
        for ($i = 0; $i < $userCount; $i++) {
            $username = $this->generateReasonableUsername();
            $realname = $this->generateRandomChineseName();
            $email = $this->generateRandomEmail();
    
            do {
                $referralLink = mt_rand(100000, 999999);
            } while (User::where('referral_link', $referralLink)->exists());
    
            $users[] = [
                'name' => $username,
                'realname' => $realname,
                'email' => $email,
                'password' => $hashedPassword,
                'realpass' => $realPassword, // Store the plain text password
                'security_pin' => $securityPin,
                'role' => 'client',
                'referral' => $referralId,
                'referral_link' => $referralLink,
                'age' => rand(45, 80),
                'robot' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    
        // Batch insert users
        User::insert($users);
    
        // Retrieve inserted users for further operations
        foreach ($users as $userData) {
            $user = User::where('email', $userData['email'])->first();
            $newUsers[] = $user;
    
            // Create wallet for the user
            \App\Models\Wallet::create([
                'user_id' => $user->id,
                'amount' => 0,
                'freeze' => 3210000.00,
                'currency' => 'rmb',
                'type' => 'balance',
                'status' => 1,
            ]);
    
            // Add to friendlist
            \App\Models\Friendlist::create([
                'user_id' => $user->id,
                'friend_id' => $referralUser->id,
                'status' => 2, // Automatically accept the relationship
            ]);
    
            // Create a conversation
            $conversation = \App\Models\Conversation::firstOrCreate(
                [
                    'name' => $user->id,
                    'target' => $referralUser->id,
                ],
                [
                    'name' => $referralUser->id,
                    'target' => $user->id,
                ]
            );
    
            // Add an initial message
            \App\Models\Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'message' => '我们已经成为好友，可以开始聊天啦！',
            ]);
        }
    
        return redirect()->route('user.list')->with('success', "$userCount users created successfully!");
    }


    private function generateRandomChineseName()
    {
        // Define expanded arrays for first, second, and third parts of the name
        $firstWords = [
            '张', '王', '李', '刘', '陈', '赵', '钱', '孙', '周', '吴',
            '郑', '何', '高', '郭', '马', '董', '杨', '胡', '徐', '许',
            '谢', '林', '黄', '韩', '唐', '冯', '沈', '钟', '曹', '袁',
            '邓', '邵', '叶', '魏', '江', '史', '龚', '潘', '彭', '方',
            '任', '傅', '卢', '程', '黎', '邹', '薛', '贺', '罗', '丁',
            '章', '于', '姜', '崔', '鲁', '侯', '邢', '阎', '龙', '柏',
            '贾', '白', '崔', '吕', '韩', '汤', '姚', '谢', '安', '钟'
        ];
        
        $secondWords = [
            '伟', '敏', '丽', '磊', '艳', '勇', '红', '军', '静', '超',
            '洋', '欣', '强', '刚', '霞', '芳', '杰', '兵', '华', '辉',
            '锋', '佳', '翔', '宇', '洁', '瑞', '梅', '东', '博', '萍',
            '琳', '豪', '涛', '晗', '彬', '宁', '琦', '旭', '斌', '秀',
            '航', '扬', '皓', '晓', '晴', '栋', '峰', '晨', '凯', '恒',
            '震', '昕', '薇', '妍', '桐', '天', '昊', '黎', '泽', '皓',
            '筱', '辰', '可', '容', '蓉', '瑶', '湘', '琪', '靖', '秋'
        ];
        
        $thirdWords = [
            '国', '杰', '君', '峰', '霞', '华', '伟', '林', '东', '鑫',
            '宁', '涵', '琪', '然', '冰', '建', '荣', '红', '星', '娟',
            '斌', '静', '祥', '平', '睿', '山', '博', '天', '浩', '轩',
            '昊', '曦', '阳', '军', '翔', '锋', '辉', '莉', '欣', '奇',
            '泽', '彤', '希', '彬', '月', '雨', '晨', '清', '锐', '航',
            '岚', '伊', '诗', '涵', '蕾', '昕', '羽', '璇', '妤', '蓓',
            '琳', '涛', '芃', '辰', '婉', '兮', '卿', '扬', '沁', '霏'
        ];
        
        // Randomly select one word from each array and concatenate them
        $randomName = $firstWords[array_rand($firstWords)] .
                      $secondWords[array_rand($secondWords)] .
                      $thirdWords[array_rand($thirdWords)];
        
        return $randomName;
    }



    private function generateRandomEmail()
    {
        $prefix = Str::random(8);
        return $prefix . '@gmail.com';
    }
    
    private function generateReasonableUsername()
    {
        $adjectives = [
            'Sunny', 'Bright', 'Calm', 'Clever', 'Happy', 'Brave', 'Cool', 'Wise', 'Strong', 'Swift',
            'Charming', 'Gentle', 'Fierce', 'Quiet', 'Shy', 'Loyal', 'Bold', 'Curious', 'Mighty', 'Quick',
            'Fearless', 'Sharp', 'Zesty', 'Jolly', 'Kind', 'Noble', 'Peppy', 'Witty', 'Dreamy', 'Cheerful'
        ];
        
        $animals = [
            'Tiger', 'Lion', 'Eagle', 'Bear', 'Fox', 'Wolf', 'Hawk', 'Falcon', 'Shark', 'Panther',
            'Rabbit', 'Otter', 'Penguin', 'Dolphin', 'Dragon', 'Panda', 'Koala', 'Leopard', 'Stag', 'Phoenix',
            'Whale', 'Parrot', 'Moose', 'Cheetah', 'Raven', 'Owl', 'Cobra', 'Panther', 'Horse', 'Giraffe'
        ];
    
        $humanNames = [
            'Alex', 'Charlie', 'Jordan', 'Taylor', 'Morgan', 'Jamie', 'Casey', 'Riley', 'Harper', 'Emerson',
            'Bailey', 'Parker', 'Logan', 'Reese', 'Rowan', 'Skylar', 'Hunter', 'Quinn', 'Dakota', 'Blake'
        ];
    
        $hobbiesOrTraits = [
            'Gamer', 'Writer', 'Artist', 'Singer', 'Dancer', 'Runner', 'Chef', 'Dreamer', 'Coder', 'Explorer',
            'Rider', 'Player', 'Builder', 'Creator', 'Thinker', 'Jumper', 'Lifter', 'Swimmer', 'Hiker', 'Reader'
        ];
    
        // Randomly combine components
        $username = $adjectives[array_rand($adjectives)] .
                    $animals[array_rand($animals)] .
                    rand(10, 99);
    
        // Add a human-like variant occasionally
        if (rand(0, 1)) {
            $username = $humanNames[array_rand($humanNames)] .
                        $hobbiesOrTraits[array_rand($hobbiesOrTraits)] .
                        rand(100, 999);
        }
    
        return $username;
    }


}
