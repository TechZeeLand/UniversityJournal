-- Font choices (names from the fonts service; edited in the Tech portal)
INSERT INTO settings (k, v, grp) VALUES
  ('font_body', 'Open Sans', 'fonts'),
  ('font_heading', 'Young Serif', 'fonts'),
  ('font_bn', 'Noto Sans Bengali', 'fonts'),
  ('font_ar', 'Noto Naskh Arabic', 'fonts'),
  ('font_weights_body', '400;700', 'fonts'),
  ('font_weights_heading', '400', 'fonts');

-- Profile fields an author must complete before publishing (edited in the Tech portal)
INSERT INTO settings (k, v, grp) VALUES
  ('profile_required_student', '["full_name","department_id","academic_session","bio","photo_path"]', 'profiles'),
  ('profile_required_teacher', '["full_name","department_id","designation","bio","photo_path"]', 'profiles');
