<?php
function get_grade_groups(): array {
    return [
        '1-5'   => 'Lớp 1 – 5 (Tiểu học)',
        '6-9'   => 'Lớp 6 – 9 (THCS)',
        '10-12' => 'Lớp 10 – 12 (THPT)',
    ];
}
function get_subjects(): array {
    return [
        '1-5' => [
            'Môn công cụ'              => ['Tiếng Việt','Toán'],
            'Xã hội & Đạo đức'         => ['Đạo đức','Lịch sử và Địa lý'],
            'Khoa học'                 => ['Tự nhiên và Xã hội','Khoa học'],
            'Công nghệ & Ngôn ngữ'     => ['Tin học và Công nghệ','Ngoại ngữ 1'],
            'Thể chất & Nghệ thuật'    => ['Giáo dục thể chất','Âm nhạc','Mỹ thuật'],
            'Hoạt động bắt buộc'       => ['Hoạt động trải nghiệm'],
        ],
        '6-9' => [
            'Công cụ & Ngôn ngữ'       => ['Ngữ văn','Toán','Ngoại ngữ 1'],
            'Môn tích hợp'             => ['Khoa học tự nhiên','Lịch sử và Địa lý'],
            'Giáo dục lối sống'        => ['Giáo dục công dân'],
            'Công nghệ & Nghệ thuật'   => ['Công nghệ','Tin học','Âm nhạc','Mỹ thuật'],
            'Thể chất'                 => ['Giáo dục thể chất'],
            'Hoạt động bắt buộc'       => ['Hoạt động trải nghiệm','Nội dung giáo dục địa phương'],
        ],
        '10-12' => [
            'Bắt buộc – Công cụ'       => ['Toán','Ngữ văn','Ngoại ngữ 1'],
            'Bắt buộc – Thể chất'      => ['Giáo dục thể chất','Giáo dục quốc phòng và an ninh'],
            'Bắt buộc – Hoạt động'     => ['Hoạt động trải nghiệm','Nội dung giáo dục địa phương'],
            'Chọn – Khoa học xã hội'   => ['Lịch sử','Địa lý','Giáo dục kinh tế và pháp luật'],
            'Chọn – Khoa học tự nhiên' => ['Vật lý','Hóa học','Sinh học'],
            'Chọn – Công nghệ'         => ['Công nghệ','Tin học','Âm nhạc','Mỹ thuật'],
        ],
    ];
}
function get_all_subjects_flat(): array {
    $all = [];
    foreach (get_subjects() as $groups)
        foreach ($groups as $items)
            foreach ($items as $s) $all[] = $s;
    return array_unique($all);
}
function subjects_for_grade(string $grade): array {
    return get_subjects()[$grade] ?? [];
}
