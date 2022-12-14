<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Photo;
use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
class PostController extends Controller
{

    public function __construct()
    {
        $this->middleware("auth");
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


    public function index()
    {
        $posts = Post::when(request("keyword"), function($q){
            $keyword = request("keyword");
            $q->orwhere("title", "like", "%$keyword%")
                ->orwhere("description", "like", "%$keyword%");
        })->when(Auth::user()->role == "author", function($q){
            $q->where("user_id", Auth::id());
        })->with(['category', 'user'])
        ->latest("id")->paginate(10)->withQueryString();
        return view("post.index", compact("posts"));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view("post.create");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StorePostRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StorePostRequest $request)
    {
        // saving post
        $post = new Post();
        $post->title = $request->title;
        $post->slug = Str::slug($request->title);
        $post->description = $request->description;
        $post->excerpt = Str::words($request->description, 50, " .....");
        $post->user_id = Auth::id();
        $post->category_id = $request->category;
        if($request->hasFile("featured_image")){
            $newName = uniqid()."_featured_image.".$request->file("featured_image")->getClientOriginalExtension();
            $post->feature_image = $newName;
            $request->file("featured_image")->storeAs("public", $newName);
        }
        $post->save();

        // saving photos
        foreach ($request->photos as $photo){
            $newName = uniqid()."_post_image.".$photo->getClientOriginalExtension();
            $photo->storeAs("public", $newName);
            $photo = new Photo();
            $photo->name = $newName;
            $photo->post_id = $post->id;
            $photo->save();
        }
        return redirect()->route("post.index");
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function show(Post $post)
    {
//        return $post->user;
        Gate::authorize("view", $post);
        return view('post.show', compact("post"));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function edit(Post $post)
    {
        Gate::authorize("update", $post);
        return view("post.edit", compact("post"));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatePostRequest  $request
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        if(Gate::denies("update", $post)){
            return abort(403, "Not allow to edit this post");
        }
        $post->title = $request->title;
        $post->slug = Str::slug($request->title);
        $post->description = $request->description;
        $post->excerpt = Str::words($request->description, 50, " .....");
        $post->user_id = Auth::id();
        $post->category_id = $request->category;
        if($request->hasFile("featured_image")){
            if(isset($post->feature_image)){
                Storage::delete($post->feature_image);
            }
            $newName = uniqid()."_featured_image.".$request->file("featured_image")->getClientOriginalExtension();
            $post->feature_image = $newName;
            $request->file("featured_image")->storeAs("public",$newName);
        }
        $post->update();

        // saving photo
        foreach ($request->photos as $photo){
            $newName = uniqid()."_post_image".$photo->getClientOriginalExtension();
            $photo->storeAs("public", $newName);
            $photo = new Photo();
            $photo->post_id = $post->id;
            $photo->name = $newName;
            $photo->save();
        }


        return redirect()->route ("post.index")->with("status", "post updated successfully");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post)
    {
        if(isset($post->feature_image)){
            Storage::delete("public/".$post->feature_image);
        }
        foreach ($post->photos as $photo){
            Storage::delete("public/".$photo->name);
            $photo->delete();
        }
        $post->delete();
        return redirect()->route("post.index")->with('status', "post deleted successfully");
    }
}
