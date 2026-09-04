<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;

class Settings
{
    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-settings-page">
            <div class="mathcourse-admin-header">
                <div>
                    <div class="mathcourse-admin-eyebrow">MathCourse · 系统</div>
                    <h1>系统设置</h1>
                    <p>查看 MathCourse 当前运行状态与版本信息。</p>
                </div>
            </div>

            <div class="mathcourse-settings-grid">
                <section class="mathcourse-settings-card mathcourse-settings-status">
                    <div class="mathcourse-settings-card-head">
                        <div class="mathcourse-settings-icon is-success"><span class="dashicons dashicons-yes-alt"></span></div>
                        <div>
                            <h2>系统状态</h2>
                            <p>核心功能当前运行正常</p>
                        </div>
                    </div>
                    <div class="mathcourse-status-line"><span class="mathcourse-status-dot"></span><strong>正常运行</strong></div>
                    <p class="mathcourse-settings-note">MathCourse 已加载，课程、课时、学员授权与学习进度模块可以正常使用。</p>
                </section>

                <section class="mathcourse-settings-card">
                    <div class="mathcourse-settings-card-head">
                        <div class="mathcourse-settings-icon"><span class="dashicons dashicons-admin-plugins"></span></div>
                        <div>
                            <h2>版本信息</h2>
                            <p>当前安装的 MathCourse 版本</p>
                        </div>
                    </div>
                    <div class="mathcourse-version-value">v<?php echo esc_html(MATHCOURSE_VERSION); ?></div>
                    <div class="mathcourse-settings-meta">MathCourse 核心插件</div>
                </section>
            </div>

            <section class="mathcourse-settings-card mathcourse-settings-info">
                <div class="mathcourse-settings-card-head">
                    <div class="mathcourse-settings-icon"><span class="dashicons dashicons-info-outline"></span></div>
                    <div>
                        <h2>运行说明</h2>
                        <p>当前版本采用 WordPress 与 Tutor LMS 的原生扩展方式。</p>
                    </div>
                </div>
                <div class="mathcourse-info-list">
                    <div><span>课程数据</span><strong>Tutor LMS</strong></div>
                    <div><span>课程管理</span><strong>MathCourse</strong></div>
                    <div><span>学习进度</span><strong>MathCourse</strong></div>
                    <div><span>视频播放</span><strong>MathCourse 播放器</strong></div>
                </div>
            </section>
        </div>
        <?php
    }
}
