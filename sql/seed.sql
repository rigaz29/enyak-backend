-- Initial settings + sample channels for testing. Replace channels with your own
-- (legal) catalog. The Apple/Mux URLs below are public test streams.
SET NAMES utf8mb4;

INSERT INTO settings (k, v) VALUES
  ('website_url',     'https://enyak.my.id'),
  ('promo_video_url', ''),
  ('min_app_version', '1.0.0'),
  ('trial_seconds',   '3600')
ON DUPLICATE KEY UPDATE v = VALUES(v);

INSERT INTO channels (name, group_title, stream_url, stream_type, is_free, sort_index) VALUES
  ('Demo Free (Apple)', 'Demo',
   'https://devstreaming-cdn.apple.com/videos/streaming/examples/img_bipbop_adv_example_fmp4/master.m3u8',
   'hls', 1, 1),
  ('Demo Paid (Mux)', 'Demo',
   'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
   'hls', 0, 2);
