<?php

/**
* 
*/
class Post extends Eloquent{

	public $primaryKey = 'p_id';
	public $timestamps = false;

	public function baseValidate(){
		$validator = Validator::make(
			['title' => $this->p_title, 'user' => $this->u_id, 'status' => $this->p_status, 'site' => $this->s_id],
			['title' => 'required|max:140', 'user' => 'required|digits_between:1,11', 'status' => 'required', 'site' => 'required|digits_between:1,11']
			);
		if($validator->fails()){
			$msg = $validator->messages();
			throw new Exception($msg->first(), 1);
		}else{
			return true;
		}
	}

	public function addPost(){
		$this->created_at = date('Y-m-d H:i:s');
		$this->p_status = 0;
		$this->baseValidate();
		if(!$this->save()){
			throw new Exception("添加帖子失败", 1);			
		}else{
			return true;
		}
	}

	public function disable(){
		$this->baseValidate();
		$this->p_status = 1;
		$this->save();
	}

	public function enable(){
		$this->baseValidate();
		$this->p_status = 0;
		$this->save();
	}

	public function addPraise(){
		$this->baseValidate();
		$this->p_praise += 1;
		$this->save();
	}

	public function delPraise(){
		$this->baseValidate();
		$this->p_praise -= 1;
		$this->save();
	}

	public function showInList(){
		$replys = [];
		if(isset($this->replys)){
			foreach ($this->replys as $reply) {
				$replys[$reply->r_id] = $reply->showInList();
			}
		}
		$data = ['title' => urldecode($this->p_title), 'content' => $this->p_content, 'post_time' => $this->created_at->format('Y-m-d H:i:s'), 'user' => $this->user->showInList(), 'replys' => $replys];
		return $data;
	}

	public function user(){
		return $this->belongsTo('User', 'u_id', 'u_id');
	}

	public function replys(){
		return $this->hasMany('PostsReplys', 'p_id', 'p_id');
	}
}