This project started with the need for tracking and confirmation codes.  I wanted a simple URL and I wanted the tracking codes to be part of a group.

It basically settles on using `time()` for the current _epoch_  `timestamp`.  

My _technical_ need for the generation of the tracking codes is around 1/min.  __If you need multiple tracking codes per second, this project is not for you!!__ 

All this project really does is, takes the `timestamp` and applies a hash function.  Where the hash, like `md5sum(time())` is split into multiple confirmation codes.  

```
example.com/timestamp
["d7e6", "d55b", "a379", "a13d", "08c2", "5d15", "faf2", "a23b"]
```

This would allow a single `timestamp` to track up to `8` items, with a single `URL`. 

