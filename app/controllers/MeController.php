<?php
/**
*
*/
class MeController extends \BaseController
{
    /**
     * get my-info
     * @author Kydz 2015-06-26
     * @return array detailed my info
     */
    public function me()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', 0);
        try {
            $user = User::chkUserByToken($token, $u_id);
            $user = User::with('bankCards.bank', 'contact', 'school')->find($user->u_id);
            $userInfo = $user->showDetail();
            $cards = $user->showBankCards();
            $contact = $user->showContact();
            $school = $user->showSchool();
            $data = ['user_info' => $userInfo, 'cards' => $cards, 'contact' => $contact, 'school' => $school];
            $re = ['result' => 2000, 'data' => $data, 'info' => '获取用户成功'];
        } catch (Exception $e) {
            $re = ['result' => 2001, 'data'=> [], 'info' => $e->getMessage()];
        }

        return Response::json($re);
    }

    /**
     * get my posts
     * @author Kydz 2015-07-04
     * @return array posts info
     */
    public function myPosts()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', 0);
        $keyWord = Input::get('key');
        try {
            $user = User::chkUserByToken($token, $u_id);
            $user = User::with([
                'posts' => function ($q) use ($keyWord) {
                    $q->where('p_status', '=', 1);
                    if (!empty($keyWord)) {
                        $q->where('p_title', 'LIKE', '%'.$keyWord.'%');
                    }
                },
                'posts.replys' => function ($q) {
                    $q->where('r_status', '=', 1);
                },
                'posts.replys.user',
                'posts.replys.toUser',
                'posts.praises',
                ])->find($user->u_id);
            $posts = $user->getPosts();
            $re = ['result' => 2000, 'data' => $posts, 'info' => '获取用户帖子成功'];
        } catch (Exception $e) {
            $re = ['result' => 2001, 'data' => [], 'info' => $e->getMessage()];
        }
        return Response::json($re);
    }

    /**
     * get my followers
     * @author Kydz 2015-07-04
     * @return json followers list
     */
    public function myFollowers()
    {
        $u_id = Input::get('u_id');
        $token = Input::get('token');

        try {
            $user = User::chkUserByToken($token, $u_id);
            $data = $this->getUserFollowers($user->u_id);
            $re = ['result' => 2000, 'data' => $data, 'info'=> '获取我的粉丝成功'];
        } catch (Exception $e) {
            $re = ['result' => 2001, 'data' => [], 'info' => $e->getMessage()];
        }
        return Response::json($re);
    }

    /**
     * get my followings
     * @author Kydz 2015-07-04
     * @return json followings list
     */
    public function myFollowings()
    {
        $u_id = Input::get('u_id');
        $token = Input::get('token');

        try {
            $user = User::chkUserByToken($token, $u_id);
            $data = $this->getUserFollowings($user->u_id);
            $re = ['result' => 2000, 'data' => $data, 'info' => '获取我关注的人成功'];
        } catch (Exception $e) {
            $re = ['result' => 2001, 'data' => [], 'info' => $e->getMessage()];
        }
        return Response::json($re);
    }

    /**
     * reset pass word
     * @author Kydz 2015-07-04
     * @return json n/a
     */
    public function resetPass()
    {
        $mobile = Input::get('mobile');
        $vcode = Input::get('vcode');
        $newPass = Input::get('pass');

        $user = User::where('u_mobile', '=', $mobile)->first();

        // chcek if mobile exsits
        if (!isset($user->u_id)) {
            return Response::json(['result' => 2001, 'data' => [], 'info' => '没有查找到与该手机号码绑定的用户']);
        }
        $phone = new Phone($mobile);
        try {
            if ($phone->authVCode($vcode)) {
                $user->u_password = $newPass;
                $user->updateUser();
            }
            $re = ['result' => 2000, 'data' => [], 'info' => '重置密码成功'];
        } catch (Exception $e) {
            $re = ['result' => 2001, 'data' => [], 'info' => $e->getMessage()];
        }

        return Response::json($re);
    }

    /**
     * replies from me
     * @author Kydz
     * @return json reply list
     */
    public function myReply()
    {
        $u_id = Input::get('u_id');
        $token = Input::get('token');
        try {
            $user = User::chkUserByToken($token, $u_id);
            $data = PostsReply::with(['post', 'toUser'])->where('u_id', '=', $u_id)->where('r_status', '=', 1)->paginate(10);
            $list = [];
            foreach ($data as $key => $reply) {
                $list[] = $reply->showInList();
            }
            $re = ['result' => 2000, 'data' => $list, 'info' => '获取我的回复成功'];
        } catch (Exception $e) {
            $re = ['result' => 2001, 'data' => [], 'info' => $e->getMessage()];
        }
        return Response::json($re);
    }

    /**
     * praised from me
     * @author Kydz
     * @return json praised list
     */
    public function myPraise()
    {
        $u_id = Input::get('u_id');
        $token = Input::get('token');
        try {
            $user = User::chkUserByToken($token, $u_id);
            $data = PostsPraise::with(['post'])->where('u_id', '=', $u_id)->paginate(10);
            $list = [];
            foreach ($data as $key => $praise) {
                $list[] = $praise->showInList();
            }
            $re = ['result' => 2000, 'data' => $list, 'info' => '获取的赞成功'];
        } catch (Exception $e) {
            $re = ['result' => 2001, 'data' => [], 'info' => $e->getMessage()];
        }
        return Response::json($re);
    }

    public function newBooth()
    {
        // base infos
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', '');
        $s_id = Input::get('s_id', '');

        // booth type
        $boothType = Input::get('type');
        // product category
        $productCate = Input::get('prod_cate');
        // booth title
        $boothTitle = Input::get('title');
        // booth position
        $boothLng = Input::get('lng');
        $boothLat = Input::get('lat');
        // product source
        $productSource = Input::get('prod_source');
        // customer group
        $cusomerGroup = Input::get('cust_group');
        // promo strategy
        $promoStratege = Input::get('promo_strategy');
        // with fund
        $withFund = Input::get('fund', 0);

        // profit ratio
        $profitRate = Input::get('profit');
        // loan amount
        $loan = Input::get('loan');
        // how to drow loan
        $laonSchema = Input::get('loan_schema', '');

        try {
            $user = User::chkUserByToken($token, $u_id);

            $chk = Booth::where('u_id', '=', $u_id)->where('b_type', '=', $boothType)->first();

            if (isset($chk->b_id)) {
                throw new Exception("您已经申请过该类店铺了, 请勿重复提交", 1);
            }

            $booth = new Booth();
            $booth->s_id = $s_id;
            $booth->u_id = $u_id;
            $booth->b_title = $boothTitle;
            $booth->b_desc = '';
            $booth->latitude = $boothLat;
            $booth->longitude = $boothLng;
            $booth->b_product_source = $productSource;
            $booth->b_product_category = $productCate;
            $booth->b_customer_group = $cusomerGroup;
            $booth->b_promo_strategy = $promoStratege;
            $booth->b_with_fund = $withFund;
            $booth->b_type = $boothType;
            $b_id = $booth->register();
            
            if ($withFund == 1) {
                $fund = new Fund();
                $fund->u_id = $u_id;
                $fund->t_apply_money = $loan;
                $fund->b_id = $b_id;
                $fund->t_profit_rate = $profitRate;
                $f_id = $fund->apply();

                $schema = 0;
                $allotedAmount = 0;

                $laonSchema = json_decode($laonSchema, true);

                if (!is_array($laonSchema)) {
                    throw new Exception("请传入正确的提款计划", 1);
                }

                foreach ($laonSchema as $key => $percentage) {
                    $percentage = $percentage / 100;
                    $schema ++;
                    if ($schema == count($laonSchema)) {
                        $amount = $loan - $allotedAmount;
                    } else {
                        $amount = $loan * $percentage;
                        $allotedAmount += $amount;
                    }
                    $repayment = new Repayment();
                    $repayment->f_id = $f_id;
                    $repayment->f_re_money = $amount;
                    $repayment->f_schema = $schema;
                    $repayment->apply();
                }
            }
            $re = ['result' => 2000, 'data' => [], 'info' => '申请成功'];
        } catch (Exception $e) {
            // clean up todo
            Booth::clearByUser($u_id);
            $f_id = Fund::clearByUser($u_id);
            Repayment::clearByFund($f_id);
            $re = ['result' => 7001, 'data' => [], 'info' => '申请失败:'.$e->getMessage()];
        }

        return Response::json($re);
    }

    public function boothList()
    {
        $u_id = Input::get('u_id', '');
        $token = Input::get('token');

        try {
            $user = User::chkUserByToken($token, $u_id);
            $data = Booth::where('u_id', '=', $u_id)->get();
            $list = [];
            foreach ($data as $key => $booth) {
                $tmp = $booth->showInList();
                $products_count = Product::where('b_id', '=', $booth->b_id)->where('p_status', '=', 1)->count();
                $tmp['prodct_count'] = $products_count;
                $list[] = $tmp;
            }
            $re = ['result' => 2000, 'data' => $list, 'info' => '获取我的所有店铺成功'];
        } catch (Exception $e) {
            $re = ['result' => 7001, 'data' => [], 'info' => '获取我的所有店铺失败:'.$e->getMessage()];
        }

        return Response::json($re);
    }

    public function booth($id)
    {
        $u_id = Input::get('u_id', 0);
        $token = Input::get('token', '');

        try {
            $user = User::chkUserByToken($token, $u_id);
            $booth = Booth::find($id);
            if (empty($booth->b_id) || $booth->u_id != $u_id) {
                throw new Exception("无法获取到请求的店铺", 1);
            }
            $now = new DateTime();
            $now->modify('-8 hours');
            $boothInfo = $booth->showDetail();
            $products = Product::where('b_id', '=', $booth->b_id)->where('p_status', '=', 1)->where('p_active_at', '<', $now->format('Y-m-d H:i:s'))->with(['quantity'])->paginate(10);
            $list = [];
            foreach ($products as $key => $product) {
                $list[] = $product->showInList();
            }
            $pagination = ['total_record' => $products->getTotal(), 'total_page' => $products->getLastPage(), 'per_page' => $products->getPerPage(), 'current_page' => $products->getCurrentPage()];
            $data = ['booth' => $boothInfo, 'products' => $list, 'pagination' => $pagination];
            $re = ['result' => 2000, 'data' => $data, 'info' => '获取我的店铺成功'];
        } catch (Exception $e) {
            $re = ['result' => 7001, 'data' => [], 'info' => '获取我的店铺失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function profileCheck()
    {
        $u_id = Input::get('u_id', 0);
        $token = Input::get('token', '');

        try {
            $user = User::chkUserByToken($token, $u_id);
            $bank = TmpUsersBankCard::checkProfile($u_id);
            $contact = TmpUsersContactPeople::checkProfile($u_id);
            $detail = TmpUsersDetails::checkProfile($u_id);
            $re = ['result' => 2000, 'data' => ['detail' => $detail, 'contact' => $contact, 'bank' => $bank], 'info' => '获取用户资料验证信息成功'];
        } catch (Exception $e) {
            $re = ['result' => 3002, 'data' => [], 'info' => '获取用户资料验证信息失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function getDetail()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', '');
        
        try {
            $user = User::chkUserByToken($token, $u_id);
            $detail = TmpUsersDetails::find($u_id);
            $data = [];
            $data['name'] = $user->u_name;
            if (!isset($detail->u_id)) {
                $data['id_num'] = '';
                $data['id_img'] = '';
                $data['home_addr'] = '';
                $data['mo_name'] = '';
                $data['mo_phone'] = '';
                $data['fa_name'] = '';
                $data['fa_phone'] = '';
            } else {
                $data['id_num'] = $detail->u_identity_number;
                $data['id_img'] = explode(',', $detail->u_identity_img);
                $data['home_addr'] = $detail->u_home_adress;
                $data['mo_name'] = $detail->u_mother_name;
                $data['mo_phone'] = $detail->u_mother_telephone;
                $data['fa_name'] = $detail->u_father_name;
                $data['fa_phone'] = $detail->u_father_telephone;
            }
            $re = ['result' => 2000, 'data' => $data, 'info' => '获取用户详细成功'];
        } catch (Exception $e) {
            $re = ['result' => 3002, 'data' => $data, 'info' => '获取用户详细失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function postDetail()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', '');

        $name = Input::get('name', '');

        $idNum = Input::get('id_num', '');
        // home address
        $homeAddr = Input::get('home_addr');
        // mother name
        $moName = Input::get('mo_name');
        // mother phone
        $moPhone = Input::get('mo_phone');
        // father name
        $faName = Input::get('fa_name');
        // father phone
        $faPhone = Input::get('fa_phone');

        $imgToken = Input::get('img_token');

        try {
            $user = User::chkUserByToken($token, $u_id);

            $user_detail = TmpUsersDetails::find($u_id);
            if (!isset($user_detail->u_id)) {
                $user_detail = new TmpUsersDetails();
            }
            if ($user_detail->u_status == 1) {
                throw new Exception("您的审核已经通过", 3002);
            }

            $user->u_name = $name;
            $user->save();

            $user_detail->u_id = $u_id;
            $user_detail->u_identity_number = $idNum;
            $user_detail->u_home_adress = $homeAddr;
            $user_detail->u_father_name = $faName;
            $user_detail->u_father_telephone = $faPhone;
            $user_detail->u_mother_name = $moName;
            $user_detail->u_mother_telephone = $moPhone;
            $user_detail->register();

            if ($imgToken) {
                $imgObj = new Img('user', $imgToken);
                $imgs = $imgObj->getSavedImg($u_id, '', true);
                $id_img = [];
                foreach ($imgs as $k => $img) {
                    if ($k == 'identity_img_front' || $k == 'identity_img_back') {
                        $id_img[] = $img;
                    }
                }
                $user_detail->u_identity_img = implode(',', $id_img);
                $user_detail->save();
            }


            $re = ['result' => 2000, 'data' => [], 'info' => '提交详细信息审核成功'];
        } catch (Exception $e) {
            TmpUsersDetails::clearByUser($u_id);
            $re = ['result' => 3002, 'data' => [], 'info' => '提交详细信息审核失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function getContact()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', '');
        
        try {
            $user = User::chkUserByToken($token, $u_id);
            $contact = TmpUsersContactPeople::find($u_id);
            $data = [];
            if (!isset($contact->u_id)) {
                $data['th_name'] = '';
                $data['th_phone'] = '';
                $data['fr_name_1'] = '';
                $data['fr_phone_1'] = '';
                $data['fr_name_2'] = '';
                $data['fr_phone_2'] = '';
                $data['stu_num'] = '';
                $data['stu_img'] = '';
                $data['school'] = '';
                $data['profession'] = '';
                $data['degree'] = '';
                $data['entry_year'] = '';
            } else {
                $data['th_name'] = $contact->u_teacher_name;
                $data['th_phone'] = $contact->u_teacher_telephone;
                $data['fr_name_1'] = $contact->u_frend_name1;
                $data['fr_phone_1'] = $contact->u_frend_telephone1;
                $data['fr_name_2'] = $contact->u_frend_name2;
                $data['fr_phone_2'] = $contact->u_frend_telephone2;
                $data['stu_num'] = $contact->u_student_number;
                $data['stu_num'] = explode(',', $contact->u_student_img);
                $data['school'] = $contact->u_school_id;
                $data['profession'] = $contact->u_prof;
                $data['degree'] = $contact->u_degree;
                $data['entry_year'] = $contact->u_entry_year;
            }
            $re = ['result' => 2000, 'data' => $data, 'info' => '获取用户详细成功'];
        } catch (Exception $e) {
            $re = ['result' => 3002, 'data' => $data, 'info' => '获取用户详细失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function postContact()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', '');

        // shcool id
        $school = Input::get('school');
        // shcool entry year
        $entryYear = Input::get('entry_year');
        // profession area
        $profession = Input::get('profession');
        // graduate degree
        $degree = Input::get('degree');

        // studen card number
        $studentNum = Input::get('stu_num');
        // teacher name
        $thName = Input::get('th_name');
        // teacher phone
        $thPhone = Input::get('th_phone');
        // friend name 1
        $frName1 = Input::get('fr_name_1');
        // friend phone 1
        $frPhone1 = Input::get('fr_phone_1');
        // friend name 2
        $frName2 = Input::get('fr_name_2');
        // friend phone 2
        $frPhone2 = Input::get('fr_phone_2');

        $imgToken = Input::get('img_token', '');

        try {
            $user = User::chkUserByToken($token, $u_id);

            $user_contact_people = TmpUsersContactPeople::find($u_id);
            if (!isset($user_contact_people->u_id)) {
                $user_contact_people = new TmpUsersContactPeople();
            }
            if ($user_contact_people->u_status == 1) {
                throw new Exception("您的审核已经通过", 3002);
            }
            $user_contact_people->u_id = $u_id;
            $user_contact_people->u_teacher_name = $thName;
            $user_contact_people->u_teacher_telephone = $thPhone;
            $user_contact_people->u_frend_name1 = $frName1;
            $user_contact_people->u_frend_telephone1 = $frPhone1;
            $user_contact_people->u_frend_name2 = $frName2;
            $user_contact_people->u_frend_telephone2 = $frPhone2;
            $user_contact_people->u_student_number = $studentNum;
            $user_contact_people->u_school_id = $school;
            $user_contact_people->u_prof = $profession;
            $user_contact_people->u_degree = $degree;
            $user_contact_people->u_entry_year = $entryYear;
            $user_contact_people->register();

            if ($imgToken) {
                $imgObj = new Img('user', $imgToken);
                $imgs = $imgObj->getSavedImg($u_id, '', true);
                $student_img = [];
                foreach ($imgs as $k => $img) {
                    if ($k == 'student_img_front' || $k == 'student_img_back') {
                        $student_img[] = $img;
                    }
                }
                $user_contact_people->u_student_img = implode(',', $student_img);
                $user_contact_people->save();
            }

            $re = ['result' => 2000, 'data' => [], 'info' => '提交学校信息成功'];
        } catch (Exception $e) {
            TmpUsersContactPeople::clearByUser($u_id);
            $re = ['result' => 3002, 'data' => [], 'info' => '提交学校信息失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function getCard()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', '');
        
        try {
            $user = User::chkUserByToken($token, $u_id);
            $card = TmpUsersBankCard::where('u_id', '=', $u_id)->first();
            if (!isset($card->u_id)) {
                $data['bank'] = '';
                $data['card_num'] = '';
                $data['card_holder'] = '';
                $data['holder_phone'] = '';
                $data['holder_ID'] = '';
            } else {
                $data['bank'] = $card->b_id;
                $data['card_num'] = $card->b_card_num;
                $data['card_holder'] = $card->b_holder_name;
                $data['holder_phone'] = $card->u_frend_telephone1;
                $data['holder_ID'] = $card->b_holder_identity;
            }
            $re = ['result' => 2000, 'data' => $data, 'info' => '获取用户银行卡成功'];
        } catch (Exception $e) {
            $re = ['result' => 3002, 'data' => $data, 'info' => '获取用户银行卡失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function postCard()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', '');
        $vcode = Input::get('vcode', '');
        $mobile = Input::get('mobile', '');

        // id bank
        $bankId = Input::get('bank', 0);
        // bank card number
        $cardNum = Input::get('card_num', '');
        // card holder name
        $cardHolderName = Input::get('card_holder', '');
        // card holder phone
        $cardHolderPhone = Input::get('holder_phone', '');
        // card holder identy
        $cardHolderID = Input::get('holder_ID', '');

        try {
            $user = User::chkUserByToken($token, $u_id);

            $phone = new Phone($mobile);
            $phone->authVCode($vcode);

            $card = TmpUsersBankCard::where('u_id', '=', $u_id)->first();
            if (!isset($card->u_id)) {
                $card = new TmpUsersBankCard();
            }
            if ($card->u_status == 1) {
                throw new Exception("您的审核已经通过", 3002);
            }
            $card->u_id = $u_id;
            $card->b_id = $bankId;
            $card->b_card_num = $cardNum;
            $card->b_holder_name = $cardHolderName;
            $card->b_holder_phone = $cardHolderPhone;
            $card->b_holder_identity = $cardHolderID;
            $card->register();
            $re = ['result' => 2000, 'data' => [], 'info' => '提交银行卡信息成功'];
        } catch (Exception $e) {
            TmpUsersBankCard::clearByUser($u_id);
            $re = ['result' => 3002, 'data' => [], 'info' => '提交银行卡信息失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function getProduct($id)
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', 0);

        try {
            $user = User::chkUserByToken($token, $u_id);
            $product = Product::find($id);
            $product->load('quantity');
            $data = $product->showDetail();
            $re = ['result' => 2000, 'data' => $data, 'info' => '获取商品成功'];
        } catch (Exception $e) {
            $re = ['result' => 7001, 'data' => $data, 'info' => '获取商品失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function postProduct()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', 0);
        $b_id = Input::get('b_id', 0);

        $prodName = Input::get('prod_name', '');
        $prodDesc = Input::get('prod_desc', '');
        $prodCost = Input::get('prod_cost', 0);
        $prodPrice = Input::get('prod_price', 0);
        $prodDiscount = Input::get('prod_discount', 0);
        $prodStock = Input::get('prod_stock', 0);
        $publish = Input::get('publish', 1);

        $imgToken = Input::get('img_token', '');

        try {
            $user = User::chkUserByToken($token, $u_id);

            $product = new Product();
            $product->b_id = $b_id;
            $product->p_title = $prodName;
            $product->u_id = $u_id;
            $product->p_cost = $prodCost;
            $product->p_price = $prodPrice;
            $product->p_discount = $prodDiscount;
            $product->p_desc = $prodDesc;
            $product->sort = 1;
            $product->p_status = $publish == 1 ? 1 : 2;
            $p_id = $product->addProduct();
            $quantity = new ProductQuantity();
            $quantity->p_id = $p_id;
            $quantity->b_id = $b_id;
            $quantity->u_id = $u_id;
            $quantity->q_total = $prodStock;
            $quantity->addQuantity();

            $re = ['result' => 2000, 'data' => [], 'info' => '添加产品成功陪'];
        } catch (Exception $e) {
            $re = ['result' => 7001, 'data' => [], 'info' => '添加产品失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function updateProductSort()
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', 0);
        $sort = Input::get('sort', '');

        try {
            $user = User::chkUserByToken($token, $u_id);

            $sortArray = json_decode($sort, true);
            if (!is_array($sortArray)) {
                throw new Exception("请传入正确的排序数据", 1);
            }
            $re = Product::updateSort($sortArray);
            $re = ['result' => 2000, 'data' => [], 'info' => '更新排序成功'];
        } catch (Exception $e) {
            $re = ['result' => 7001, 'data' => [], 'info' => '更新排序失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }

    public function productOn($id)
    {
        $token = Input::get('token', '');
        $u_id = Input::get('u_id', 0);
        $on = Input::get('on', 1);

        try {
            $user = User::chkUserByToken($token, $u_id);
            $product = Product::find($id);
            if (!isset($product->p_id)) {
                throw new Exception("您请求的商品不存在", 1);
            }
            $product->p_status = $on == 1 ? 1 : 2;
            $product->save();
            $re = ['result' => 2000, 'data' => [], 'info' => '产品操作成功'];
        } catch (Exception $e) {
            $re = ['result' => 7001, 'data' => [], 'info' => '产品操作失败:'.$e->getMessage()];
        }
        return Response::json($re);
    }
}
