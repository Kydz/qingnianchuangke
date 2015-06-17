<?php

class PostController extends \BaseController {

	private $user = null;

	private function chkUser(){
		$token = Input::get('token');
		$user = User::where('u_token', '=', $token)->first();
		if(!isset($user->u_id)){
			return Response::json(['result' => false, 'data' => [], 'msg' => '您的登录已过期，请重新登录']);
		}else{
			$this->user = $user;
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index(){
		$page = Input::get('page', 0);
		$data = [];
		try {
			$posts = Post::with([
				'replys' => function($query){
					$query->where('r_status', '=', 0);
				},
				'user'])->where('p_status', '=', 0)->get();
			foreach ($posts as $post) {
				$data[$post->p_id] = $post->showInList();
			}
			$re = ['result' => true, 'data' => [$data], 'info' => '读取帖子成功'];
		} catch (Exception $e) {
			$re = ['result' => false, 'data' => $data, 'info' => $e->getMessage()];
		}
		return Response::json($re);
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(){
		$this->chkUser();
		$title = Input::get('title');
		$longitude = Input::get('longitude');
		$latitude = Input::get('latitude');
		$address = Input::get('address');
		$site_id = 1;
		$post = new Post();
		$post->u_id = $this->user->u_id;
		$post->p_title = $title;
		$post->s_id = $site_id;
		$post->p_longitude = $longitude;
		$post->p_latitude = $latitude;
		$post->p_address = $address;
		try {
			$post->addPost();
			$re = ['result' => true, 'data' => [], 'info' => '添加成功'];
		} catch (Exception $e) {
			$re = ['result' =>  false, 'data' => [], 'info' => $e->getMessage()];
		}
		return Response::json($re);
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id){
		$post = Post::with([
			'replys' => function($query){
				$query->where('r_status', '=', 0);
			},
			'user'])
		->where('p_id', '=', $id)->where('p_status', '=', 0)->first();
		try {
			$data = $post->showInList();
			$re = ['result' => true, 'data' => [$data], 'info' => '读取帖子成功'];
		} catch (Exception $e) {
			$re = ['result' => false, 'data' => [], 'info' => $e->getMessage()];
		}
		return Response::json($re);
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id){
		$this->chkUser();
		$post = Post::find($id);
		$content = Input::get('content');
		$reply = new PostsReplys();
		$reply->p_id = $id;
		$reply->u_id = $this->user->u_id;
		$reply->r_content = $content;
		$reply->r_status = 0;
		$reply->created_at = date('Y-m-d H:i:s');
		try {
			$reply->addReply();
			$re = ['result' => true, 'data' => [], 'info' => '回复成功'];
		} catch (Exception $e) {
			$re = ['result' => false, 'data' => [], 'info' => $e->getMessage()];
		}
		return Response::json($re);
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id){
		$this->chkUser();
		$post = Post::find($id);
		try {
			$post->disable();
			$re = ['result' =>true, 'data' => [], 'info' => '删除成功'];
		} catch (Exception $e) {
			$re = ['result' =>false, 'data' => [], 'info' => $e->getMessage()];
		}
		return Response::json($re);
	}

	/**
	 * add/del praise
	 * @author Kydz 2015-06-17
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function praise($id){
		$this->chkUser();
		$type = Input::get('type');
		$post = Post::find($id);
		try {
			$result = true;
			if($type == 1){
				$post->addPraise();
				$info = '点赞成功';
			}elseif($type == 2){
				$post->delPraise();
				$info = '取消赞成功';
			}else{
				$result = false;
				$info = '操作失败';
			}
			$re = ['result' => $result, 'data' => [], 'info' => $info];
		} catch (Exception $e) {
			$re = ['result' => false, 'data' => [], 'info' => $e->getMessage()];
		}

		return Response::json($re);

	}

	public function disableReply($id){
		$reply = PostsReplys::find($id);
		try {
			$reply->disable();
			$re = ['result' => true, 'data' => [], 'info' => ['评论删除成功']];
		} catch (Exception $e) {
			$re = ['result' => false, 'data' => [], 'info' => $e->getMessage()];
		}
		return Response::json($re);
	}


}